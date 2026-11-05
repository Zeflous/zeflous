<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Closure;
use Zef\Framework\Database\ConnectionException;
use Zef\Framework\Database\IndexSchema;
use Zef\Framework\Database\QueryException;

/**
 * The indexes question's row fold: the dialect's answered rows
 * folded into the reflection's index values. SQLite's PRAGMA list
 * carries its column names through a nested per-index walk, so the
 * SQLite fold receives the column-name seam as its own closure -- the
 * orchestration's nested ask, handed in so the fold itself stays a
 * pure row fold; the two `information_schema` dialects answer one
 * ordered row set, grouped here by index name with the uniqueness
 * read from the group's first row (every row of one index carries
 * the same verdict -- the grouping keeps the answer the driver gave).
 *
 * @internal
 */
final class SchemaIndexes
{
    /**
     * Folds the dialect's answered rows into the index values.
     *
     * @param list<array<string, mixed>>    $rows        the dialect's index rows
     * @param Closure(string): list<string> $columnNames the per-index column-name seam the
     *                                                   SQLite fold walks (never reached by the
     *                                                   `information_schema` dialects)
     *
     * @return list<IndexSchema>
     *
     * @throws QueryException      when any answered cell refuses the narrowing
     * @throws ConnectionException when the driver owns no dialect
     */
    public static function fold(string $driver, array $rows, Closure $columnNames): array
    {
        return match ($driver) {
            'sqlite' => self::fromSqlite($rows, $columnNames),
            'mysql', 'pgsql' => self::fromInformationSchema($rows),
            default => throw new ConnectionException("Unsupported database driver '{$driver}'."),
        };
    }

    /**
     * The SQLite fold: `PRAGMA index_list`'s own keys, one value per
     * answered row, the covered columns asked through the seam.
     *
     * @param list<array<string, mixed>>    $rows        the PRAGMA's answered rows
     * @param Closure(string): list<string> $columnNames the per-index column-name seam
     *
     * @return list<IndexSchema>
     */
    private static function fromSqlite(array $rows, Closure $columnNames): array
    {
        $indexes = [];

        foreach ($rows as $row) {
            $indexName = SchemaCells::string($row, 'name');

            $indexes[] = new IndexSchema(
                $indexName,
                $columnNames($indexName),
                SchemaFlags::verdict($row, 'unique'),
            );
        }

        return $indexes;
    }

    /**
     * The `information_schema` fold: the ordered rows grouped by
     * index name, the columns in the spell's own order, the
     * uniqueness from the group's first row.
     *
     * @param list<array<string, mixed>> $rows the aliased answered rows
     *
     * @return list<IndexSchema>
     */
    private static function fromInformationSchema(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[SchemaCells::string($row, 'name')][] = $row;
        }

        $indexes = [];

        foreach ($grouped as $indexRows) {
            $columns = [];

            foreach ($indexRows as $indexRow) {
                $columns[] = SchemaCells::string($indexRow, 'column_name');
            }

            $indexes[] = new IndexSchema(
                SchemaCells::string($indexRows[0], 'name'),
                $columns,
                SchemaFlags::verdict($indexRows[0], 'is_unique'),
            );
        }

        return $indexes;
    }
}
