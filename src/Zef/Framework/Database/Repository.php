<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * Thin table-data-gateway base class composed on {@see QueryBuilder}.
 *
 * Deliberately NOT an ORM: no identity map, no lazy relations, no change
 * tracking — just typed convenience over the query builder so repositories
 * stay in the application layer while the domain stays persistence-agnostic.
 *
 * The gateway's three judgements live in their own folds: the criteria
 * walk ({@see RepositoryCriteria}), the aggregate spelling and decode
 * ({@see RepositoryAggregate}), and the page wiring
 * ({@see RepositoryPage}).
 */
abstract class Repository
{
    private readonly string $table;

    public function __construct(
        protected readonly ConnectionInterface $connection,
        string $table,
    ) {
        // Validate eagerly so a typo surfaces at construction, not first query.
        new SqlIdentifier()->table($table);
        $this->table = $table;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insert(array $row): int
    {
        return $this->connection->execute($this->qb()->insertRows([$row])->build());
    }

    /**
     * @return null|array<string, mixed>
     */
    public function find(int|string $id, string $primaryKey = 'id'): ?array
    {
        return $this->connection->fetchOne(
            $this->qb()->where($primaryKey, '=', $id)->limit(1)->build(),
        );
    }

    /**
     * AND-equality lookup; null values become IS NULL.
     *
     * @param array<string, mixed> $criteria
     *
     * @return null|array<string, mixed>
     */
    public function findOneBy(array $criteria): ?array
    {
        return $this->connection->fetchOne(
            RepositoryCriteria::apply($this->qb(), $criteria)->limit(1)->build(),
        );
    }

    /**
     * @param array<string, mixed>       $criteria
     * @param null|array<string, string> $orderBy  column => ASC|DESC
     *
     * @return list<array<string, mixed>>
     *
     * @throws QueryException when an offset is requested without a limit
     */
    public function findBy(array $criteria = [], ?array $orderBy = null, ?int $limit = null, int $offset = 0): array
    {
        $queryBuilder = RepositoryPage::apply(
            RepositoryCriteria::apply($this->qb(), $criteria),
            $orderBy,
            $limit,
            $offset,
        );

        return $this->connection->fetchAll($queryBuilder->build());
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int
    {
        return RepositoryAggregate::count($this->connection, $this->qb(), $criteria);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function exists(array $criteria = []): bool
    {
        return $this->count($criteria) > 0;
    }

    /**
     * @param array<string, mixed> $pairs
     * @param array<string, mixed> $criteria
     *
     * @throws QueryException when the criteria is empty (the update would
     *                        be unbounded)
     */
    public function update(array $pairs, array $criteria): int
    {
        if ($criteria === []) {
            throw new QueryException('update() requires at least one criterion.');
        }

        return $this->connection->execute(
            RepositoryCriteria::apply($this->qb()->update($pairs), $criteria)->build(),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @throws QueryException when the criteria is empty (the delete would
     *                        be unbounded)
     */
    public function delete(array $criteria): int
    {
        if ($criteria === []) {
            throw new QueryException('delete() requires at least one criterion.');
        }

        return $this->connection->execute(
            RepositoryCriteria::apply($this->qb()->delete(), $criteria)->build(),
        );
    }

    /**
     * Fresh builder bound to this repository's table.
     */
    protected function qb(): QueryBuilder
    {
        return QueryBuilder::table($this->table);
    }
}
