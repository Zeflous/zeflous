<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The update payload's validation fold: the shape rules a SET pair map
 * must satisfy before the builder accepts it -- non-empty and every
 * value inside the bound scalar answer set
 * ({@see QueryValues::assertScalar()}).
 *
 * @internal
 */
final class QueryUpdatePairs
{
    /**
     * Validates and accepts an update payload.
     *
     * @param array<string, mixed> $pairs
     *
     * @return array<string, mixed>
     */
    public static function accept(SqlIdentifier $sqlIdentifier, array $pairs): array
    {
        if ($pairs === []) {
            throw new QueryException('update() requires at least one column => value pair.');
        }

        /** @var mixed $value one pair's value, judged by the scalar guard below */
        foreach ($pairs as $column => $value) {
            $sqlIdentifier->quote($column, 'column');
            QueryValues::assertScalar($value, "update() value for '{$column}'");
        }

        return $pairs;
    }
}
