<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The comparison grammar's value-bound fold: the predicate that
 * judges an OPERATOR from {@see QueryValues}' closed list, guards
 * the value beside it, and renders `column <op> ?` with the value
 * bound after it.
 *
 * One spelling per consumer lane: the fluent where verb
 * ({@see QueryWhere::condition()}), the having verb
 * ({@see QueryHaving::comparison()}), and the comparison leaf
 * ({@see ConditionComparison}) all render through here, so the
 * operator vocabulary, its refusal, and the null-equality guard
 * live once. The identifier-bound column comparison inlines in its
 * own leaf ({@see ConditionColumnComparison}) -- its trivial
 * assembly has no other consumer.
 *
 * @internal
 */
final class QueryComparison
{
    /**
     * Renders `column <op> ?` with the value bound after it.
     */
    public static function comparison(
        SqlIdentifier $sqlIdentifier,
        SqlExpression|string $column,
        string $operator,
        mixed $value,
    ): QueryFragment {
        $op = QueryValues::assertComparisonOperator($operator);
        if ($value === null && \in_array($op, ['=', '<>'], true)) {
            throw new QueryException(
                "Use whereNull() with its negated flag instead of comparing '{$column}' with {$op} null.",
            );
        }

        QueryValues::assertScalar($value, "condition value for '{$column}'");

        if ($column instanceof SqlExpression) {
            return new QueryFragment($column->__toString() . ' ' . $op . ' ?', [$value]);
        }

        return new QueryFragment($sqlIdentifier->columnPath($column) . ' ' . $op . ' ?', [$value]);
    }
}
