<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The where-family's shaped predicates: the pure folds that turn a
 * membership list, a bounded range, a NULL state, or a LIKE pattern
 * into a rendered {@see QueryFragment}.
 *
 * The value-bound comparison lives in its own fold ({@see QueryComparison},
 * the operator grammar's unit) and the column-bound shape inlines in its
 * leaf ({@see ConditionColumnComparison}); every fold here is branch-pure
 * over its inputs -- the scalar guards live in {@see QueryValues}, the
 * identifier quoting in {@see SqlIdentifier}, and the fragment list that
 * joins the answers lives in {@see QueryWhereState}.
 *
 * @internal
 */
final class QueryPredicate
{
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
