<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The applied-versions bookkeeping: the `zef_migrations` table's
 * schema, the ascending read of applied versions, and the row verbs a
 * step's transaction commits (the apply's insert, the rollback's
 * delete).
 *
 * @internal
 */
final class MigrationBookkeeping
{
    /** The applied-versions table's name. */
    public const string MIGRATIONS_TABLE = 'zef_migrations';

    /**
     * Creates the bookkeeping table when it does not exist yet.
     */
    public static function ensureTable(ConnectionInterface $connection): void
    {
        $connection->execute(SqlQuery::raw(
            'CREATE TABLE IF NOT EXISTS "' . self::MIGRATIONS_TABLE . '" ('
            . '"version" VARCHAR(14) NOT NULL PRIMARY KEY, '
            . '"name" VARCHAR(128) NOT NULL, '
            . '"applied_at" INTEGER NOT NULL)',
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
        self::ensureTable($connection);
        $rows = $connection->fetchAll(
            QueryBuilder::table(self::MIGRATIONS_TABLE)->select('version')->orderBy('version')->build(),
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
     * step's transaction commits beside the migration's own up()).
     */
    public static function appliedStatement(MigrationInterface $migration, int $appliedAt): SqlQuery
    {
        return QueryBuilder::table(self::MIGRATIONS_TABLE)
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
     * down()).
     */
    public static function removalStatement(string $version): SqlQuery
    {
        return QueryBuilder::table(self::MIGRATIONS_TABLE)->where('version', '=', $version)->delete()->build();
    }
}
