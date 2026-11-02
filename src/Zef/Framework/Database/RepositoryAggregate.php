<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The gateway's aggregate fold: the COUNT spelling the lean verb
 * surface mandates -- an explicit {@see SqlExpression} carrying the
 * `AS aggregate` alias -- and the decode of the driver's answer, every
 * numeric spelling folding to the integer count and anything else
 * halting as the contract break it is.
 *
 * @internal
 */
final class RepositoryAggregate
{
    /**
     * Answers the criteria-qualified row count.
     *
     * @param array<string, mixed>|Condition $criteria
     */
    public static function count(
        ConnectionInterface $connection,
        QueryBuilder $queryBuilder,
        array|Condition $criteria,
    ): int {
        $row = $connection->fetchOne(
            RepositoryCriteria::apply($queryBuilder, $criteria)
                ->select(new SqlExpression('COUNT(*) AS aggregate'))->build(),
        );
        $value = $row['aggregate'] ?? null;
        if (!\is_int($value) && !\is_string($value) && !\is_float($value)) {
            throw new QueryException('count() returned no aggregate value.');
        }

        return (int) $value;
    }
}
