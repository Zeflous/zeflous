<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Closure;

/**
 * The runner's two lock-scoped walks, over the runner's own fixed
 * context (connection, lock machine, clock, default TTL): the migrate
 * loop (pending migrations applied in ascending order, each step's
 * transactional apply preceded by the lock heartbeat renewed with the
 * effective TTL) and the rollback loop (the target versions reverted,
 * newest first, the same heartbeat discipline). Both walks read the
 * applied bookkeeping themselves — inside the lock scope their caller
 * opens.
 *
 * @internal
 */
final readonly class MigrationRun
{
    /**
     * @param ConnectionInterface $connection    the runner's connection
     * @param MigrationLock       $migrationLock the runner's lock machine
     * @param Closure(): int      $now           the runner's wall-clock seconds provider
     * @param float               $defaultTtl    the runner's default lock TTL
     */
    public function __construct(
        private ConnectionInterface $connection,
        private MigrationLock $migrationLock,
        private Closure $now,
        private float $defaultTtl,
    ) {
    }

    /**
     * Applies every pending migration, in ascending version order.
     *
     * @param array<string, MigrationInterface> $registered the runner's registrations
     *
     * @return list<string> the versions applied now
     */
    public function apply(array $registered): array
    {
        $appliedVersions = MigrationBookkeeping::readApplied($this->connection);
        $appliedNow = [];
        foreach (MigrationPending::migrations($registered, $appliedVersions) as $migration) {
            $this->migrationLock->renew(MigrationTtl::effective($migration, $this->defaultTtl));
            MigrationStep::apply($this->connection, $migration, ($this->now)());
            $appliedNow[] = $migration->version();
        }

        return $appliedNow;
    }

    /**
     * Reverts the $steps most recent applied migrations, newest first.
     *
     * @param array<string, MigrationInterface> $registered the runner's registrations
     *
     * @return list<string> the versions rolled back
     */
    public function revert(array $registered, int $steps): array
    {
        $appliedVersions = MigrationBookkeeping::readApplied($this->connection);
        $rolled = [];
        foreach (MigrationRollback::targets($appliedVersions, $steps) as $version) {
            $migration = MigrationRollback::migration($registered, $version);
            $this->migrationLock->renew(MigrationTtl::effective($migration, $this->defaultTtl));
            MigrationStep::revert($this->connection, $migration, $version);
            $rolled[] = $version;
        }

        return $rolled;
    }
}
