<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The join clause's rendering fold: the closed join-type vocabulary and
 * the `JOIN ... ON a op b` spelling over the quoting grammar.
 *
 * @internal
 */
final class QueryJoin
{
    private const array JOIN_TYPES = ['inner', 'left', 'right', 'cross'];

    /**
     * @param string $type one of inner, left, right, cross
     */
    public static function render(
        SqlIdentifier $sqlIdentifier,
        SqlExpression|string $table,
        string $first,
        string $operator,
        string $second,
        string $type,
    ): string {
        $type = mb_strtolower($type);
        if (!\in_array($type, self::JOIN_TYPES, true)) {
            throw new QueryException(
                "Unknown join type '{$type}' (allowed: " . implode(', ', self::JOIN_TYPES) . ').',
            );
        }

        $op = QueryValues::assertComparisonOperator($operator);
        $keyword = $type === 'inner' ? 'INNER JOIN' : mb_strtoupper($type . ' JOIN');

        return $keyword . ' ' . $sqlIdentifier->table($table)
            . ' ON ' . $sqlIdentifier->columnPath($first)
            . ' ' . $op . ' ' . $sqlIdentifier->columnPath($second);
    }
}
