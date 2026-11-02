<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Closure;

/**
 * Versioned migration runner.
 *
 * - tracks applied versions in the `zef_migrations` table
 *   ({@see MigrationBookkeeping});
 * - applies pending migrations in ascending version order
 *   ({@see MigrationPending}), each inside its own transaction
 *   ({@see MigrationStep}: up() + bookkeeping commit or roll back
 *   together) — the migrate walk itself lives in {@see MigrationRun},
 *   lock-scoped by the lock machine's own {@see MigrationLock::scoped()};
 * - supports rollback(N) in descending order ({@see MigrationRollback});
 * - guards concurrent runners with a single-row lock table
 *   ({@see MigrationLock} over `zef_migrations_lock`); a lock whose age
 *   exceeds the TTL recorded ON THE ROW is considered stale and stolen
 *   ({@see MigrationLockAge});
 * - renews (heartbeats) the lock with the effective TTL right before
 *   each migration step ({@see MigrationTtl}), so a legitimately slow
 *   step (huge ALTER TABLE, index rebuild) is never stolen mid-flight —
 *   a migration may raise its own headroom via
 *   MigrationInterface::getLockTtl().
 *
 * The clock is injectable ($now returning unix seconds) so lock expiry
 * and heartbeat renewal are deterministically testable.
 */
final class Migrator
{
    private readonly MigrationLock $migrationLock;

    private readonly MigrationRun $migrationRun;

    /** @var array<string, MigrationInterface> */
    private array $registered = [];

    /**
     * @param null|Closure(): int $now wall-clock unix seconds provider
     *
     * @throws MigrationException when the lock TTL is not positive
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        ?Closure $now = null,
        private readonly float $lockTtlSeconds = 300.0,
    ) {
        MigrationTtl::assertDefault($this->lockTtlSeconds);
        $now ??= time(...);
        $this->migrationLock = new MigrationLock($this->connection, $now, $this->lockTtlSeconds);
        $this->migrationRun = new MigrationRun($this->connection, $this->migrationLock, $now, $this->lockTtlSeconds);
    }

    public function register(MigrationInterface $migration): void
    {
        $this->registered = MigrationRegistration::accept($migration, $this->registered);
    }

    /**
     * @return list<string> versions recorded as applied, ascending
     */
    public function applied(): array
    {
        return MigrationBookkeeping::readApplied($this->connection);
    }

    /**
     * @return list<string> versions that migrate() would apply right now
     */
    public function plan(): array
    {
        return MigrationPending::plan($this->registered, $this->applied());
    }

    /**
     * Apply every pending migration. Returns the versions applied now.
     *
     * @return list<string>
     */
    public function migrate(): array
    {
        return $this->migrationLock->scoped(
            fn (): array => $this->migrationRun->apply($this->registered),
        );
    }

    /**
     * Roll back the $steps most recent applied migrations (descending).
     * Returns the versions rolled back.
     *
     * @return list<string>
     *
     * @throws MigrationException when $steps is not positive, exceeds the
     *                            applied count, or names an unregistered version
     */
    public function rollback(int $steps = 1): array
    {
        MigrationRollback::assertSteps($steps);

        return $this->migrationLock->scoped(
            fn (): array => $this->migrationRun->revert($this->registered, $steps),
        );
    }
}
