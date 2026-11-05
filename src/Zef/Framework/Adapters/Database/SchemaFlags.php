<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\QueryException;

/**
 * The reflected rows' verdict fold: the one narrowing every
 * schema-reflection row fold reads its boolean answers through. The
 * three flag spellings the dialects answer fold to one verdict
 * vocabulary here -- MySQL's int expressions (`1`/`0`), SQLite's
 * PRAGMA zeros and ones, PostgreSQL's native bools: true and 1 answer
 * true, false and 0 answer false, a key the driver never answered
 * answers the honest negative, and anything else refuses with the
 * key named -- the {@see PdoRows} discipline, one question over.
 *
 * @internal
 */
final class SchemaFlags
{
    /**
     * Narrows one answered cell to the verdict its aliased key
     * promises.
     *
     * @param array<string, mixed> $row one reflected row
     *
     * @throws QueryException when the cell is outside the flag vocabulary
     */
    public static function verdict(array $row, string $key): bool
    {
        /** @var mixed $value the answered cell, judged by the guards below */
        $value = $row[$key] ?? null;

        if (\in_array($value, [true, 1], true)) {
            return true;
        }

        if (\in_array($value, [false, 0, null], true)) {
            return false;
        }

        throw new QueryException("The driver answered a non-flag '{$key}' cell in a reflected row.");
    }
}
