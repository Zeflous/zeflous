<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\QueryException;

/**
 * The reflected rows' identity-cell fold: the one vocabulary every
 * schema-reflection row fold reads its named and numbered answers
 * through -- the string cells (names) and the integer cells
 * (ordinals, positions), each narrowed with the key named so a
 * broken driver shape surfaces at the fold instead of leaking a
 * mixed value into the reflection's value objects: the {@see PdoRows}
 * discipline one level up, over the dialect's aliased keys. The
 * verdict cells live in their own fold ({@see SchemaFlags}), the
 * declared-default cells in the columns fold that owns the question.
 *
 * @internal
 */
final class SchemaCells
{
    /**
     * Narrows one answered cell to the string its aliased key promises.
     *
     * @param array<string, mixed> $row one reflected row
     *
     * @throws QueryException when the cell is absent or not a string
     */
    public static function string(array $row, string $key): string
    {
        /** @var mixed $value the answered cell, judged by the guard below */
        $value = $row[$key] ?? null;

        if (!\is_string($value)) {
            throw self::refusal('string', $key);
        }

        return $value;
    }

    /**
     * Narrows one answered cell to the integer its aliased key
     * promises, or null when the driver answered no cell at all (the
     * LEFT JOIN's honest miss).
     *
     * @param array<string, mixed> $row one reflected row
     *
     * @throws QueryException when the cell is neither null nor an integer
     */
    public static function integer(array $row, string $key): ?int
    {
        /** @var mixed $value the answered cell, judged by the guards below */
        $value = $row[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!\is_int($value)) {
            throw self::refusal('integer', $key);
        }

        return $value;
    }

    /**
     * The family's one refusal spelling: the cell the driver answered
     * outside the vocabulary its aliased key promised, named by the
     * vocabulary and the key -- one fold, so the narrowings share a
     * doctrine without sharing a token stream.
     */
    private static function refusal(string $kind, string $key): QueryException
    {
        return new QueryException("The driver answered a non-{$kind} '{$key}' cell in a reflected row.");
    }
}
