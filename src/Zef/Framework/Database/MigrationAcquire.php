<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The lock acquisition's walk: create the lock table, claim the single
 * row, and when the claim is refused (the row is already there), decide
 * the steal against the row's own recorded TTL and take the lock over.
 * A pure statement walk over the connection — the reentrancy guard and
 * the depth bookkeeping stay on the {@see MigrationLock} machine.
 *
 * @internal
 */
final class MigrationAcquire
{
    /**
     * Acquires the lock row on the holder's behalf.
     *
     * @throws TransactionException when the declared holder is still
     *                              inside its own recorded TTL
     * @throws QueryException       when the lock row is malformed
     */
    public static function walk(ConnectionInterface $connection, int $now, float $ttl): void
    {
        MigrationLockRow::ensureTable($connection);

        try {
            MigrationLockRow::claim($connection, $now, $ttl);
        } catch (QueryException) {
            $row = MigrationLockRow::read($connection);
            MigrationLockAge::assertStealable($row ?? [], $now);
            MigrationLockRow::rewrite($connection, $now, $ttl);
        }
    }
}
