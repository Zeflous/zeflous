<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The gateway's criteria walk: the AND-equality map with its null
 * cells spelling IS NULL -- the one judgement a criteria map carries
 * -- or one {@see Condition} tree, the composed lane the
 * Domain-Driven direction names: groups, OR, column comparisons and
 * negations arriving as values through the same walk the map always
 * rode.
 *
 * @internal
 */
final class RepositoryCriteria
{
    /**
     * Applies the criteria onto the builder: one where predicate per
     * map cell (null cells becoming whereNull), or the condition tree
     * applied through the facade's widened where verb -- never a
     * parallel rendering, the one entry the lane owns.
     *
     * @param array<string, mixed>|Condition $criteria
     */
    public static function apply(QueryBuilder $queryBuilder, array|Condition $criteria): QueryBuilder
    {
        if ($criteria instanceof Condition) {
            return $queryBuilder->where($criteria);
        }

        /** @var mixed $value one criterion's cell, judged by the null guard below */
        foreach ($criteria as $column => $value) {
            if ($value === null) {
                $queryBuilder->whereNull($column);
            } else {
                $queryBuilder->where($column, '=', $value);
            }
        }

        return $queryBuilder;
    }
}
