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
                "Use whereNull() with its negated flag instead of comparing '{$column}' with {$op} null.",
            );
        }

        QueryValues::assertScalar($value, "condition value for '{$column}'");

        if ($column instanceof SqlExpression) {
            return new QueryFragment($column->__toString() . ' ' . $op . ' ?', [$value]);
        }

        return new QueryFragment($sqlIdentifier->columnPath($column) . ' ' . $op . ' ?', [$value]);
    }

    /**
     * Renders `first <op> second` -- one comparison between two columns
     * of the same row, both operands quoted through the identifier
     * grammar, no bound values. The condition-object lane's spelling of
     * the column-vs-column form ({@see ConditionColumnComparison}); the
     * operator rides the same closed list every comparison answers.
     */
    public static function columnComparison(
        SqlIdentifier $sqlIdentifier,
        string $first,
        string $operator,
        string $second,
    ): QueryFragment {
        $op = QueryValues::assertComparisonOperator($operator);

        return new QueryFragment(
            $sqlIdentifier->columnPath($first) . ' ' . $op . ' ' . $sqlIdentifier->columnPath($second),
        );
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
     * Renders `column IS [NOT] NULL` -- the suffix spelling the NULL
     * predicate has always carried, now the predicate grammar's own
     * fold so the leaf ({@see ConditionNull}) and the verb
     * ({@see QueryWhere::nullPredicate()}) share one spelling.
     */
    public static function nullPredicate(
        SqlIdentifier $sqlIdentifier,
        string $column,
        bool $negated,
    ): QueryFragment {
        $suffix = $negated ? ' IS NOT NULL' : ' IS NULL';

        return new QueryFragment($sqlIdentifier->columnPath($column) . $suffix);
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
