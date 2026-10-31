<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The aggregate sugar's rendering: the closed function vocabulary and
 * the `<FUNC>(column) AS aggregate` expression it answers.
 *
 * @internal
 */
final class QueryAggregate
{
    private const array AGGREGATE_FUNCTIONS = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];

    /**
     * Answers the aggregate select expression, `COUNT(*)` included.
     */
    public static function expression(SqlIdentifier $sqlIdentifier, string $function, string $column): SqlExpression
    {
        $func = mb_strtoupper($function);
        if (!\in_array($func, self::AGGREGATE_FUNCTIONS, true)) {
            throw new QueryException(
                "Unknown aggregate function '{$function}' (allowed: "
                . implode(', ', self::AGGREGATE_FUNCTIONS) . ').',
            );
        }

        $inner = $column === '*' ? '*' : $sqlIdentifier->columnPath($column);

        return new SqlExpression($func . '(' . $inner . ') AS aggregate');
    }
}
