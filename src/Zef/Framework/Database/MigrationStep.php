<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The migration step's transactional apply/revert: the one place a
 * migration's own body and its bookkeeping row meet inside one
 * transaction — the up() and its record commit or roll back together,
 * and so do the down() and its erasure.
 *
 * @internal
 */
final class MigrationStep
{
    /**
     * Applies the migration and records its version, atomically.
     */
    public static function apply(ConnectionInterface $connection, MigrationInterface $migration, int $appliedAt): void
    {
        $connection->transaction(
            static function (ConnectionInterface $connection) use ($migration, $appliedAt): void {
                $migration->up($connection);
                $connection->execute(MigrationBookkeeping::appliedStatement($migration, $appliedAt));
            },
        );
    }

    /**
     * Reverts the migration and erases its bookkeeping row, atomically.
     */
    public static function revert(ConnectionInterface $connection, MigrationInterface $migration, string $version): void
    {
        $connection->transaction(
            static function (ConnectionInterface $connection) use ($migration, $version): void {
                $migration->down($connection);
                $connection->execute(MigrationBookkeeping::removalStatement($version));
            },
        );
    }
}
