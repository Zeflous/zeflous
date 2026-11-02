<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The gateway's criteria walk: AND-equality over the query builder's
 * where family, with a null cell spelling IS NULL -- the one judgement
 * a criteria map carries.
 *
 * @internal
 */
final class RepositoryCriteria
{
    /**
     * Applies the criteria map onto the builder, one where predicate
     * per cell, null cells becoming whereNull.
     *
     * @param array<string, mixed> $criteria
     */
    public static function apply(QueryBuilder $queryBuilder, array $criteria): QueryBuilder
    {
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
