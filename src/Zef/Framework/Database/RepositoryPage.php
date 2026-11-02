<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The gateway's page wiring: the order-by map applied onto the select,
 * and the limit/offset pair the builder's own verb spells -- an offset
 * riding limit's parameter, never standing without one.
 *
 * @internal
 */
final class RepositoryPage
{
    /**
     * Applies the ordering and the paging onto the builder.
     *
     * @param null|array<string, string> $orderBy column => ASC|DESC
     *
     * @throws QueryException when an offset is requested without a limit
     */
    public static function apply(QueryBuilder $queryBuilder, ?array $orderBy, ?int $limit, int $offset): QueryBuilder
    {
        foreach ($orderBy ?? [] as $column => $direction) {
            $queryBuilder->orderBy($column, $direction);
        }

        if ($limit !== null) {
            $queryBuilder->limit($limit, $offset);
        } elseif ($offset !== 0) {
            throw new QueryException('offset() requires a limit.');
        }

        return $queryBuilder;
    }
}
