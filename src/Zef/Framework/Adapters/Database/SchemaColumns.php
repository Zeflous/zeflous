<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\ColumnSchema;
use Zef\Framework\Database\ConnectionException;
use Zef\Framework\Database\QueryException;

/**
 * The columns question's row fold: the dialect's answered rows
 * folded into the reflection's column values -- the SQLite PRAGMA
 * shape and the aliased `information_schema` shape, one fold each,
 * so the driver's own spelling survives untouched. The declared type
 * travels as the driver spells it; the default travels in the
 * driver's own literal spelling (`'hi'` stays quoted, `NULL` stays
 * the word, an expression default stays its own SQL); SQLite's
 * zero-or-one `notnull` folds onto the nullability and its
 * zero-or-ordinal `pk` onto the position, with the non-member's zero
 * folded onto null.
 *
 * @internal
 */
final class SchemaColumns
{
    /**
     * Folds the dialect's answered rows into the column values.
     *
     * @param list<array<string, mixed>> $rows the dialect's answered rows
     *
     * @return list<ColumnSchema>
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
     * The SQLite fold: `PRAGMA table_info`'s own keys.
     *
     * @param list<array<string, mixed>> $rows the PRAGMA's answered rows
     *
     * @return list<ColumnSchema>
     */
    private static function fromSqlite(array $rows): array
    {
        $columns = [];

        foreach ($rows as $row) {
            $columns[] = new ColumnSchema(
                SchemaCells::string($row, 'name'),
                SchemaCells::string($row, 'type'),
                self::sqliteNullable($row),
                self::declaredDefault($row),
                self::sqlitePosition($row),
            );
        }

        return $columns;
    }

    /**
     * The `information_schema` fold: the aliased canonical keys.
     *
     * @param list<array<string, mixed>> $rows the aliased answered rows
     *
     * @return list<ColumnSchema>
     */
    private static function fromInformationSchema(array $rows): array
    {
        $columns = [];

        foreach ($rows as $row) {
            $columns[] = new ColumnSchema(
                SchemaCells::string($row, 'name'),
                SchemaCells::string($row, 'declared_type'),
                SchemaFlags::verdict($row, 'is_nullable'),
                self::declaredDefault($row),
                SchemaCells::integer($row, 'pk_position'),
            );
        }

        return $columns;
    }

    /**
     * SQLite's zero-or-one `notnull` cell folded onto the nullability
     * -- the vocabulary the PRAGMA answers, refused loudly outside
     * it, because a `notnull` cell that answers neither zero nor one
     * is a driver answering a shape no table spelled.
     *
     * @param array<string, mixed> $row one PRAGMA-answered row
     */
    private static function sqliteNullable(array $row): bool
    {
        return match (SchemaCells::integer($row, 'notnull')) {
            0 => true,
            1 => false,
            default => throw new QueryException(
                'The driver answered a notnull cell outside the zero-or-one vocabulary.',
            ),
        };
    }

    /**
     * SQLite's `pk` cell folded onto the position: the non-member's
     * zero onto null, the 1-based ordinal onto itself -- and a MISSING
     * cell refused loudly, because SQLite always answers the ordinal
     * (a row without it is a driver answering a shape no table
     * spelled, the {@see PdoRows} doctrine one fold over).
     *
     * @param array<string, mixed> $row one PRAGMA-answered row
     */
    private static function sqlitePosition(array $row): ?int
    {
        return match ($position = SchemaCells::integer($row, 'pk')) {
            null => throw new QueryException("The driver answered a missing 'pk' cell in a reflected row."),
            0 => null,
            default => $position,
        };
    }

    /**
     * The declared default's own narrowing: the `dflt_value` cell
     * folded to the scalar-or-null the column value carries -- the
     * {@see PdoRows} narrowing's promise re-asserted at the fold,
     * so a row folded by hand answers the same refusal a driver's
     * break would. The question lives here because this fold is its
     * one consumer: the driver's own literal spelling (''hi'' stays
     * quoted, `NULL` stays the word, an expression default stays its
     * own SQL) travels untouched.
     *
     * @param array<string, mixed> $row one answered row
     *
     * @return null|bool|float|int|string the declared default, or null when absent
     */
    private static function declaredDefault(array $row): bool|float|int|string|null
    {
        /** @var mixed $value the answered cell, judged by the guard below */
        $value = $row['dflt_value'] ?? null;

        if ($value !== null && !\is_scalar($value)) {
            throw new QueryException("The driver answered a non-scalar 'dflt_value' cell in a reflected row.");
        }

        return $value;
    }
}
