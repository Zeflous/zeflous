<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Closure;

/**
 * The migration runner's single-row lock machine: the held-flag
 * bookkeeping around the acquire / steal / heartbeat-renew / release
 * walk ({@see MigrationAcquire} owns the walk itself), over the
 * connection the runner holds and the clock the runner injected.
 *
 * The reentrancy guard keeps one Migrator instance from acquiring
 * twice; the release's no-op when the lock is not held is the
 * release-after-failure path the runner's finally blocks walk.
 *
 * @internal
 */
final class MigrationLock
{
    private bool $lockHeld = false;

    /**
     * @param ConnectionInterface $connection     the runner's connection
     * @param Closure(): int      $now            wall-clock unix seconds provider
     * @param float               $lockTtlSeconds the default TTL heartbeated
     *                                            onto the row for steps without their own override
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly Closure $now,
        private readonly float $lockTtlSeconds,
    ) {
    }

    /**
     * Acquires the lock: the claim/steal walk, plus this instance's
     * reentrancy guard.
     *
     * @throws TransactionException when this instance already holds the
     *                              lock, or when the declared holder is still inside its TTL
     * @throws QueryException       when the lock row is malformed
     */
    public function acquire(): void
    {
        if ($this->lockHeld) {
            throw new TransactionException('Migration lock is already held by this Migrator instance.');
        }

        MigrationAcquire::walk($this->connection, ($this->now)(), $this->lockTtlSeconds);
        $this->lockHeld = true;
    }

    /**
     * Runs $fn under the lock: acquired before, released after — the
     * failure paths included (the release the runner's finally blocks
     * used to walk, owned by the machine that owns the lock).
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public function scoped(callable $fn): mixed
    {
        $this->acquire();

        try {
            return $fn();
        } finally {
            $this->release();
        }
    }

    /**
     * Heartbeat: refresh the lock row with the effective TTL so other
     * runners never see this holder as stale while work progresses.
     */
    public function renew(float $ttl): void
    {
        if (!$this->lockHeld) {
            return;
        }

        MigrationLockRow::rewrite($this->connection, ($this->now)(), $ttl);
    }

    /**
     * Releases the lock: delete the single row. A no-op when the lock
     * is not held.
     */
    public function release(): void
    {
        if (!$this->lockHeld) {
            return;
        }

        $this->lockHeld = false;
        MigrationLockRow::remove($this->connection);
    }
}
