<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\ConnectionException;
use Zef\Framework\Database\ForeignKeySchema;
use Zef\Framework\Database\QueryException;

/**
 * The foreign-keys question's row fold: the dialect's answered rows
 * grouped into the reflection's foreign-key values. SQLite's
 * `foreign_key_list` groups by its ordinal `id` with the `from`/`to`
 * cells paired in the answer's own `seq` order and answers no
 * constraint name at all -- the value carries null, never a
 * fabricated name; the two `information_schema` dialects answer one
 * ordered row set grouped by constraint name, the referenced table
 * read from the group's first row (every row of one constraint
 * carries the same table -- the grouping keeps the answer the driver
 * gave).
 *
 * @internal
 */
final class SchemaForeignKeys
{
    /**
     * Folds the dialect's answered rows into the foreign-key values.
     *
     * @param list<array<string, mixed>> $rows the dialect's answered rows
     *
     * @return list<ForeignKeySchema>
     *
     * @throws QueryException      when any answered cell refuses the narrowing
     * @throws ConnectionException when the driver owns no dialect
     */
    public static function fold(string $driver, array $rows): array
    {
        return match ($driver) {
            'sqlite' => self::fromSqlite($rows),
            'mysql', 'pgsql' => self::fromInformationSchema($rows),
            default => throw new ConnectionException("Unsupported database driver '{$driver}'."),
        };
    }

    /**
     * The SQLite fold: `PRAGMA foreign_key_list`'s own keys, grouped
     * by the ordinal `id`, the paired columns in the answer's own
     * order, the referenced table from the group's first row.
     *
     * @param list<array<string, mixed>> $rows the PRAGMA's answered rows
     *
     * @return list<ForeignKeySchema>
     */
    private static function fromSqlite(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[self::sqliteConstraintId($row)][] = $row;
        }

        $foreignKeys = [];

        foreach ($grouped as $constraintRows) {
            $columns = [];
            $referencedColumns = [];

            foreach ($constraintRows as $constraintRow) {
                $columns[] = SchemaCells::string($constraintRow, 'from');
                $referencedColumns[] = SchemaCells::string($constraintRow, 'to');
            }

            $foreignKeys[] = new ForeignKeySchema(
                null,
                $columns,
                SchemaCells::string($constraintRows[0], 'table'),
                $referencedColumns,
            );
        }

        return $foreignKeys;
    }

    /**
     * The SQLite grouping key: the ordinal `id` the PRAGMA groups
     * its constraint rows by, narrowed to the int an array key
     * demands -- a missing cell is a driver answering a shape the
     * fold refuses to guess at.
     *
     * @param array<string, mixed> $row one PRAGMA-answered row
     */
    private static function sqliteConstraintId(array $row): int
    {
        $id = SchemaCells::integer($row, 'id');

        if ($id === null) {
            throw new QueryException("The driver answered a missing 'id' cell in a reflected row.");
        }

        return $id;
    }

    /**
     * The `information_schema` fold: the ordered rows grouped by
     * constraint name, the paired columns in the spell's own order,
     * the constraint's own name carried as answered.
     *
     * @param list<array<string, mixed>> $rows the aliased answered rows
     *
     * @return list<ForeignKeySchema>
     */
    private static function fromInformationSchema(array $rows): array
    {
        $byConstraint = [];

        foreach ($rows as $row) {
            $byConstraint[SchemaCells::string($row, 'name')][] = $row;
        }

        $foreignKeys = [];

        foreach ($byConstraint as $constraintRows) {
            $columns = [];
            $referencedColumns = [];

            foreach ($constraintRows as $constraintRow) {
                $columns[] = SchemaCells::string($constraintRow, 'column_name');
                $referencedColumns[] = SchemaCells::string($constraintRow, 'referenced_column_name');
            }

            $foreignKeys[] = new ForeignKeySchema(
                SchemaCells::string($constraintRows[0], 'name'),
                $columns,
                SchemaCells::string($constraintRows[0], 'referenced_table_name'),
                $referencedColumns,
            );
        }

        return $foreignKeys;
    }
}
