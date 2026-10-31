<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The where-family's predicate grammar: the pure folds that turn one
 * column/operator/value triple (or an IN list, a BETWEEN pair, a LIKE
 * pattern) into a rendered {@see QueryFragment}.
 *
 * Every fold is branch-pure over its inputs -- the operator and scalar
 * guards live in {@see QueryValues}, the identifier quoting in
 * {@see SqlIdentifier}, and the fragment list that joins the answers
 * lives in {@see QueryWhereState}.
 *
 * @internal
 */
final class QueryPredicate
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
                "Use whereNull()/whereNotNull() instead of comparing '{$column}' with {$op} null.",
            );
        }

        QueryValues::assertScalar($value, "condition value for '{$column}'");

        if ($column instanceof SqlExpression) {
            return new QueryFragment($column->__toString() . ' ' . $op . ' ?', [$value]);
        }

        return new QueryFragment($sqlIdentifier->columnPath($column) . ' ' . $op . ' ?', [$value]);
    }

    /**
     * Renders `column [NOT] IN (...)` -- a placeholder per list member,
     * or a subquery's SQL with its bindings merged after ours.
     *
     * @param list<mixed>|QueryBuilder $values
     */
    public static function inList(
        SqlIdentifier $sqlIdentifier,
        string $column,
        array|QueryBuilder $values,
        bool $negated,
    ): QueryFragment {
        $not = '';
        if ($negated) {
            $not = 'NOT ';
        }

        if ($values instanceof QueryBuilder) {
            $sub = $values->build();

            return new QueryFragment(
                $sqlIdentifier->columnPath($column) . ' ' . $not . 'IN (' . $sub->sql . ')',
                $sub->params,
            );
        }

        if ($values === []) {
            throw new QueryException('whereIn() requires a non-empty list of values.');
        }

        $placeholders = [];
        $params = [];

        /** @var mixed $value one member of the list, judged by the scalar guard below */
        foreach ($values as $value) {
            QueryValues::assertScalar($value, 'whereIn() value');
            /** @var null|bool|int|float|string|SqlExpression $value one validated member's bound spelling */
            $params[] = $value;
            $placeholders[] = '?';
        }

        return new QueryFragment(
            $sqlIdentifier->columnPath($column) . ' ' . $not . 'IN (' . implode(', ', $placeholders) . ')',
            $params,
        );
    }

    /**
     * Renders `column BETWEEN ? AND ?` with low then high bound.
     */
    public static function between(
        SqlIdentifier $sqlIdentifier,
        string $column,
        mixed $low,
        mixed $high,
    ): QueryFragment {
        QueryValues::assertScalar($low, 'whereBetween() low');
        QueryValues::assertScalar($high, 'whereBetween() high');

        return new QueryFragment($sqlIdentifier->columnPath($column) . ' BETWEEN ? AND ?', [$low, $high]);
    }

    /**
     * Renders `column LIKE ?` -- with the wildcards escaped and the
     * ESCAPE clause emitted when the caller asks for it.
     */
    public static function like(
        SqlIdentifier $sqlIdentifier,
        string $column,
        string $pattern,
        bool $escapeWildcards,
    ): QueryFragment {
        if ($pattern === '') {
            throw new QueryException('whereLike() pattern must not be empty.');
        }

        $escape = '';
        if ($escapeWildcards) {
            $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $pattern);
            $escape = " ESCAPE '\\'";
        }

        return new QueryFragment($sqlIdentifier->columnPath($column) . ' LIKE ?' . $escape, [$pattern]);
    }
}
