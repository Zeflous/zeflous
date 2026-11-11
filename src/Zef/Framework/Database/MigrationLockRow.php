<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The lock row's verb fold: the single-row lock table's schema and the
 * row verbs the lock machine issues — claim, rewrite (the heartbeat
 * renewal and the steal's takeover), remove, and the read — pure
 * statements over the connection, no judgement of their own (the age
 * judgement the steal needs lives in {@see MigrationLockAge}), every
 * spell quoted in the dialect the connection's driver word answers
 * ({@see IdentifierQuoting::forDriver()}, the issue #778 vocabulary).
 *
 * @internal
 */
final class MigrationLockRow
{
    /** The single-row lock table's name. */
    public const string LOCK_TABLE = 'zef_migrations_lock';

    /**
     * Creates the lock table when it does not exist yet, quoted in
     * the connection's dialect.
     */
    public static function ensureTable(ConnectionInterface $connection): void
    {
        $sqlIdentifier = new SqlIdentifier(IdentifierQuoting::forDriver($connection->driver()));
        $connection->execute(SqlQuery::raw(
            'CREATE TABLE IF NOT EXISTS ' . $sqlIdentifier->quote(self::LOCK_TABLE) . ' ('
            . $sqlIdentifier->quote('id') . ' INTEGER NOT NULL PRIMARY KEY, '
            . $sqlIdentifier->quote('locked_at') . ' INTEGER NOT NULL, '
            . $sqlIdentifier->quote('ttl') . ' REAL NOT NULL)',
        ));
    }

    /**
     * Claims the lock by inserting the single row.
     */
    public static function claim(ConnectionInterface $connection, int $now, float $ttl): void
    {
        $connection->execute(
            QueryBuilder::table(self::LOCK_TABLE, null, IdentifierQuoting::forDriver($connection->driver()))
                ->insertRows([['id' => 1, 'locked_at' => $now, 'ttl' => $ttl]])->build(),
        );
    }

    /**
     * Rewrites the single row (the heartbeat renewal, and the steal's
     * takeover).
     */
    public static function rewrite(ConnectionInterface $connection, int $now, float $ttl): void
    {
        $connection->execute(
            QueryBuilder::table(self::LOCK_TABLE, null, IdentifierQuoting::forDriver($connection->driver()))
                ->update(['locked_at' => $now, 'ttl' => $ttl])->where('id', '=', 1)->build(),
        );
    }

    /**
     * Removes the single row (the release).
     */
    public static function remove(ConnectionInterface $connection): void
    {
        $connection->execute(
            QueryBuilder::table(self::LOCK_TABLE, null, IdentifierQuoting::forDriver($connection->driver()))
                ->where('id', '=', 1)->delete()->build(),
        );
    }

    /**
     * Reads the single row as fetched, or null when no lock is held.
     *
     * @return null|array<string, mixed>
     */
    public static function read(ConnectionInterface $connection): ?array
    {
        $rows = $connection->fetchAll(
            QueryBuilder::table(self::LOCK_TABLE, null, IdentifierQuoting::forDriver($connection->driver()))
                ->select('locked_at', 'ttl')->where('id', '=', 1)->build(),
        );

        return $rows[0] ?? null;
    }
}
