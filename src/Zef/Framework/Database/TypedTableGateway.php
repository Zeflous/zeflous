<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;

/**
 * The typed table gateway: the gateway that REQUIRES a {@see TypeMap}
 * and overrides the five verbs the map carries -- the seam the value
 * vocabulary's widening rides. Writes encode the mapped columns at
 * query build (the encoded spelling lands in the {@see SqlQuery}
 * params -- the builder and the adapter untouched); reads decode them
 * after the port's own narrowing, per the map ({@see TypeMapEncode}
 * and {@see TypeMapDecode} the two directions' folds). Zero adapter changes is the design's own
 * test of the seam's placement: the port stays raw, exactly the
 * narrowing it always answered, and the base's raw verbs stay exactly
 * the spells main landed -- the typed gateway spells the five the map
 * carries, every other verb inherits untouched.
 *
 * The map is the third constructor argument the base never sees: a
 * hand-declared vocabulary, or composed from the reflected declared
 * types ({@see ReflectedTypeMap::fromSchema()} over the reflected
 * {@see TableSchema}). Every table the host does not wire this way
 * keeps the raw narrowing -- opt-in per table, never a global mode.
 *
 * @api
 */
final class TypedTableGateway extends Repository
{
    public function __construct(
        ConnectionInterface $connection,
        string $table,
        private readonly TypeMap $typeMap,
    ) {
        parent::__construct($connection, $table);
    }

    /**
     * @param array<string, mixed> $row
     */
    #[Override]
    public function insert(array $row): int
    {
        return $this->connection->execute(
            $this->qb()->insertRows([TypeMapEncode::row($this->typeMap, $row)])->build(),
        );
    }

    /**
     * @return null|array<string, mixed>
     */
    #[Override]
    public function find(int|string $id, string $primaryKey = 'id'): ?array
    {
        $row = $this->connection->fetchOne(
            $this->qb()->where($primaryKey, '=', $id)->limit(1)->build(),
        );

        return $row === null ? null : TypeMapDecode::row($this->typeMap, $row);
    }

    /**
     * @param array<string, mixed>|Condition $criteria
     *
     * @return null|array<string, mixed>
     */
    #[Override]
    public function findOneBy(array|Condition $criteria): ?array
    {
        $row = $this->connection->fetchOne(
            RepositoryCriteria::apply($this->qb(), $criteria)->limit(1)->build(),
        );

        return $row === null ? null : TypeMapDecode::row($this->typeMap, $row);
    }

    /**
     * @param array<string, mixed>|Condition $criteria
     * @param null|array<string, string>     $orderBy  column => ASC|DESC
     *
     * @return list<array<string, mixed>>
     *
     * @throws QueryException when an offset is requested without a limit
     */
    #[Override]
    public function findBy(
        array|Condition $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        int $offset = 0,
    ): array {
        $queryBuilder = RepositoryPage::apply(
            RepositoryCriteria::apply($this->qb(), $criteria),
            $orderBy,
            $limit,
            $offset,
        );

        return TypeMapDecode::rows($this->typeMap, $this->connection->fetchAll($queryBuilder->build()));
    }

    /**
     * @param array<string, mixed>           $pairs
     * @param array<string, mixed>|Condition $criteria
     *
     * @throws QueryException when the map criteria is empty (the update
     *                        would be unbounded) -- a Condition tree is
     *                        never empty by construction, so the guard
     *                        only bites on the map lane
     */
    #[Override]
    public function update(array $pairs, array|Condition $criteria): int
    {
        if ($criteria === []) {
            throw new QueryException('update() requires at least one criterion.');
        }

        return $this->connection->execute(
            RepositoryCriteria::apply(
                $this->qb()->update(TypeMapEncode::row($this->typeMap, $pairs)),
                $criteria,
            )->build(),
        );
    }
}
