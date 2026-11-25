<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\DatabaseException;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryBuilder;

/**
 * The PDO-backed delivery store's entry-row fold: the delivery
 * table's own write and removal verbs, the SQL twin of the file
 * answer's files lane ({@see DeliveryFiles}) -- every pending row
 * the store holds passes through here, so the row's shape (the
 * identity, the record the Event lane's encoder spells, the
 * ready-at moment) is spelled once for the admission's batch, the
 * requeue's retry and the operator's own eye.
 *
 * The batch write is ONE multi-row INSERT, the engine's own
 * all-or-nothing: a batch the engine cannot hold whole admits
 * NOTHING, the statement's own atomicity the mid-write
 * compensation the Redis answer spells by hand -- SQL never lands
 * half a statement. The removal is one DELETE per spent entry,
 * whose absent row answers idempotently: settling a settled entry
 * is the no-op the at-least-once arithmetic already assumes.
 */
final readonly class PdoDeliveryEntries
{
    private IdentifierQuoting $identifierQuoting;

    public function __construct(
        private ConnectionInterface $connection,
        private PdoDeliveryTables $pdoDeliveryTables,
    ) {
        $this->identifierQuoting = IdentifierQuoting::forDriver($connection->driver());
    }

    /**
     * Writes the pending rows one statement holds: the whole batch
     * admitted or refused together, the engine's own boundary.
     *
     * @param list<array{entry_id: string, record: string, ready_at: int}> $rows
     *                                                                           the rows one admission or
     *                                                                           one requeue spelled
     *
     * @throws DatabaseException when the engine cannot hold the
     *                           batch whole
     */
    public function writeRows(array $rows): void
    {
        $this->connection->execute(
            QueryBuilder::table(
                $this->pdoDeliveryTables->deliveryTable()->name,
                null,
                $this->identifierQuoting,
            )->insertRows($rows)->build(),
        );
    }

    /**
     * Removes one spent entry's row -- delivered, retried under a
     * fresh identity, or dead-lettered. The absent row is the
     * idempotent no-op: the engine answers zero affected rows and
     * never an error.
     *
     * @throws DatabaseException when the engine refuses the
     *                           removal
     */
    public function removeEntry(string $entryId): void
    {
        $this->connection->execute(
            QueryBuilder::table(
                $this->pdoDeliveryTables->deliveryTable()->name,
                null,
                $this->identifierQuoting,
            )
                ->delete()
                ->where('entry_id', '=', $entryId)
                ->build(),
        );
    }

    /**
     * Reads the rows the claimed identities spell -- the records
     * half of the due answer, one row per identity the table still
     * holds. An identity whose row is absent (the stale drainer's
     * late settle) is simply missing from the answer: the judgment
     * that follows a missing row is the records fold's own
     * doctrine, never the read's.
     *
     * @param list<string> $entryIds the identities the claim fold won
     *
     * @return list<array<string, mixed>> the rows the table answered
     *
     * @throws DatabaseException when the engine refuses the
     *                           read
     */
    public function recordsFor(array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        return $this->connection->fetchAll(
            QueryBuilder::table(
                $this->pdoDeliveryTables->deliveryTable()->name,
                null,
                $this->identifierQuoting,
            )
                ->select('entry_id', 'record')
                ->whereIn('entry_id', $entryIds)
                ->build(),
        );
    }
}
