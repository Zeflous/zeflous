<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The applied-versions bookkeeping: the `zef_migrations` table's
 * schema, the ascending read of applied versions, and the row verbs a
 * step's transaction commits (the apply's insert, the rollback's
 * delete) -- every spell in the dialect the connection's own driver
 * word answers ({@see IdentifierQuoting::forDriver()}, the issue
 * #778 vocabulary: the bookkeeping rides the same connection the
 * migrations do).
 *
 * @internal
 */
final class MigrationBookkeeping
{
    /** The applied-versions table's name. */
    public const string MIGRATIONS_TABLE = 'zef_migrations';

    /**
     * Creates the bookkeeping table when it does not exist yet,
     * quoted in the connection's dialect.
     */
    public static function ensureTable(ConnectionInterface $connection): void
    {
        $sqlIdentifier = new SqlIdentifier(IdentifierQuoting::forDriver($connection->driver()));
        $connection->execute(SqlQuery::raw(
            'CREATE TABLE IF NOT EXISTS ' . $sqlIdentifier->quote(self::MIGRATIONS_TABLE) . ' ('
            . $sqlIdentifier->quote('version') . ' VARCHAR(14) NOT NULL PRIMARY KEY, '
            . $sqlIdentifier->quote('name') . ' VARCHAR(128) NOT NULL, '
            . $sqlIdentifier->quote('applied_at') . ' INTEGER NOT NULL)',
        ));
    }

    /**
     * Answers the versions recorded as applied, ascending — the table
     * ensured first, so a fresh database answers an empty list.
     *
     * @return list<string>
     *
     * @throws QueryException when a bookkeeping row carries a
     *                        non-string version
     */
    public static function readApplied(ConnectionInterface $connection): array
    {
        $identifierQuoting = IdentifierQuoting::forDriver($connection->driver());
        self::ensureTable($connection);
        $rows = $connection->fetchAll(
            QueryBuilder::table(self::MIGRATIONS_TABLE, null, $identifierQuoting)
                ->select('version')
                ->orderBy('version')
                ->build(),
        );
        $versions = [];
        foreach ($rows as $row) {
            $version = $row['version'] ?? null;
            if (!\is_string($version)) {
                throw new QueryException('Migration bookkeeping row has a non-string version.');
            }

            $versions[] = $version;
        }

        return $versions;
    }

    /**
     * The statement that records a version as applied (the row the
     * step's transaction commits beside the migration's own up()),
     * quoted in the dialect the caller's connection answers.
     */
    public static function appliedStatement(
        MigrationInterface $migration,
        int $appliedAt,
        IdentifierQuoting $identifierQuoting,
    ): SqlQuery {
        return QueryBuilder::table(self::MIGRATIONS_TABLE, null, $identifierQuoting)
            ->insertRows([[
                'version' => $migration->version(),
                'name' => $migration->name(),
                'applied_at' => $appliedAt,
            ]])->build()
        ;
    }

    /**
     * The statement that erases a version's bookkeeping row (the row
     * the rollback's transaction deletes beside the migration's own
     * down()), quoted in the dialect the caller's connection answers.
     */
    public static function removalStatement(
        string $version,
        IdentifierQuoting $identifierQuoting,
    ): SqlQuery {
        return QueryBuilder::table(self::MIGRATIONS_TABLE, null, $identifierQuoting)
            ->where('version', '=', $version)
            ->delete()
            ->build()
        ;
    }
}
