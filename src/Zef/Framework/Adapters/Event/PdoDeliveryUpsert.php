<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TableName;

/**
 * The PDO-backed delivery store's conflict-tail write seam: the one
 * composition every upsert the store spells rides -- the builder's
 * own INSERT spelling (the identifier quoting, the param order, the
 * multi-row validation the family's engine already owns) with the
 * dialect's conflict tail appended, so the only SQL this adapter
 * ever spells beyond the builder is the clause the three drivers
 * spell apart, and it lives in exactly one place.
 *
 * Two tails, two doctrines: {@see insertOnce()} is the idempotent
 * join (a present primary key is the no-op the doctrine already
 * assumes -- the attribution book's per-entry row, the quarantine
 * book's membership), and {@see insertRewrite()} is the rewrite
 * (a present primary key means the SAME story carried whole, never
 * doubled -- the dead lane's row under the latest death).
 */
final readonly class PdoDeliveryUpsert
{
    private IdentifierQuoting $identifierQuoting;

    public function __construct(
        private ConnectionInterface $connection,
        private PdoDeliveryDialect $pdoDeliveryDialect,
    ) {
        $this->identifierQuoting = IdentifierQuoting::forDriver($connection->driver());
    }

    /**
     * Writes one row whose present primary key is the no-op the
     * doctrine assumes -- the idempotent join, never a doubling and
     * never an error the at-least-once redelivery would have to
     * answer for.
     *
     * @param TableName            $tableName  the book the row joins
     * @param array<string, mixed> $row        the column => value map, the builder's own row grammar
     * @param string               $primaryKey the column the conflict is keyed by
     *
     * @throws \Zef\Framework\Database\DatabaseException when the
     *                                                   engine refuses the write
     */
    public function insertOnce(TableName $tableName, array $row, string $primaryKey): void
    {
        $this->connection->execute(
            $this->withTail(
                $tableName,
                $row,
                $this->pdoDeliveryDialect->insertOnceTail($primaryKey),
            ),
        );
    }

    /**
     * Writes one row whose present primary key means the same story
     * rewritten: the one rewrite column carried whole under the
     * fresh write, the row never doubled.
     *
     * @param TableName            $tableName  the lane the row lands in
     * @param array<string, mixed> $row        the column => value map, the builder's own row grammar
     * @param string               $primaryKey the column the conflict is keyed by
     * @param string               $column     the column the rewrite carries
     *
     * @throws \Zef\Framework\Database\DatabaseException when the
     *                                                   engine refuses the write
     */
    public function insertRewrite(TableName $tableName, array $row, string $primaryKey, string $column): void
    {
        $this->connection->execute(
            $this->withTail(
                $tableName,
                $row,
                $this->pdoDeliveryDialect->insertRewriteTail($primaryKey, $column),
            ),
        );
    }

    /**
     * The one composition: the builder's INSERT for the row, the
     * conflict tail appended to its spelling, the params the builder
     * ordered carried unchanged.
     *
     * @param array<string, mixed> $row
     */
    private function withTail(TableName $tableName, array $row, string $tail): SqlQuery
    {
        $sqlQuery = QueryBuilder::table($tableName->name, null, $this->identifierQuoting)
            ->insertRows([$row])
            ->build()
        ;

        return new SqlQuery($sqlQuery->sql . ' ' . $tail, $sqlQuery->params);
    }
}
