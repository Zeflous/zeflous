<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\SqlExpression;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DrainTrace;
use Zef\Framework\Event\DrainTraceEncoder;
use Zef\Framework\Event\ListenerIdentity;

/**
 * The PDO-backed delivery store's books and journal: the attribution
 * table, the quarantine table and the drain journal -- the durable
 * re-spelling of the process-local books, answered over the same
 * connection the entry lifecycle spells. The attribution book is
 * one row per attributed entry (the insert-once tail the upsert
 * seam carries: the at-least-once redelivery of a crashed pass
 * re-attributes the SAME entry, never doubles the book -- the
 * moment deliberately not the book's to carry, the dead record
 * carries the whole story); the quarantine book is one row per
 * contained identity (the membership IS the containment, idempotent
 * by the insert-once tail the same seam carries); the journal is
 * one row per completed pass, the record's name a freshly minted
 * entry-style identity (two passes at the same now are two rows,
 * never one overwritten) and the record the Event lane's own trace
 * encoder spells -- the same bytes the file journal writes and the
 * Redis list appends, the record the trace either side of the store
 * choice.
 */
final readonly class PdoDeliveryBooks
{
    private IdentifierQuoting $identifierQuoting;

    private DrainTraceEncoder $drainTraceEncoder;

    public function __construct(
        private ConnectionInterface $connection,
        private PdoDeliveryTables $pdoDeliveryTables,
        private PdoDeliveryUpsert $pdoDeliveryUpsert,
    ) {
        $this->identifierQuoting = IdentifierQuoting::forDriver($connection->driver());
        $this->drainTraceEncoder = new DrainTraceEncoder();
    }

    /**
     * Attributes one spent entry's death to the listener's identity
     * -- one row joined to the attribution book, keyed by the
     * entry's own identity so the re-attribution the at-least-once
     * redelivery spells is the no-op the book's own doctrine
     * assumes.
     */
    public function recordAttributedDeath(
        DeliveryEntryId $deliveryEntryId,
        ListenerIdentity $listenerIdentity,
    ): void {
        $this->pdoDeliveryUpsert->insertOnce(
            $this->pdoDeliveryTables->attributedTable(),
            [
                'entry_id' => $deliveryEntryId->spelling(),
                'listener_identity' => $listenerIdentity->spelling(),
            ],
            'entry_id',
        );
    }

    /**
     * How many attributed deaths the book holds for the listener's
     * identity -- the count the quarantine threshold reads.
     *
     * @throws InvalidArgumentException when the engine refuses the
     *                                  count, or answers a shape the count contract does not
     *                                  spell
     */
    public function deadLettersAttributedTo(ListenerIdentity $listenerIdentity): int
    {
        $row = $this->connection->fetchOne(
            QueryBuilder::table(
                $this->pdoDeliveryTables->attributedTable()->name,
                null,
                $this->identifierQuoting,
            )
                ->select(new SqlExpression('COUNT(*) AS attributed'))
                ->where('listener_identity', '=', $listenerIdentity->spelling())
                ->build(),
        );

        $value = $row['attributed'] ?? null;

        if (!\is_int($value) && !\is_string($value) && !\is_float($value)) {
            throw new InvalidArgumentException(
                'The attributed count returned no aggregate value.',
            );
        }

        return (int) $value;
    }

    /**
     * Records the containment act -- the identity joined to the
     * quarantine book, idempotent by the insert-once tail: an
     * already-quarantined identity is the no-op the containment's
     * own doctrine assumes, and restoring a listener is the
     * operator's own judgment (the row is the operator's to remove,
     * never the regime's to lift).
     */
    public function quarantineListener(ListenerIdentity $listenerIdentity): void
    {
        $this->pdoDeliveryUpsert->insertOnce(
            $this->pdoDeliveryTables->quarantineTable(),
            ['listener_identity' => $listenerIdentity->spelling()],
            'listener_identity',
        );
    }

    /**
     * Whether the listener's identity is quarantined -- the filter
     * every future defer reads, and the guard that keeps one
     * containment from being counted twice. A fresh process reads
     * the book's own answer: the quarantine a previous process
     * earned survives the boundary.
     */
    public function isListenerQuarantined(ListenerIdentity $listenerIdentity): bool
    {
        return $this->connection->fetchOne(
            QueryBuilder::table(
                $this->pdoDeliveryTables->quarantineTable()->name,
                null,
                $this->identifierQuoting,
            )
                ->select(new SqlExpression('1 AS quarantined'))
                ->where('listener_identity', '=', $listenerIdentity->spelling())
                ->build(),
        ) !== null;
    }

    /**
     * Journals one completed drain pass -- the five counts that are
     * the regime's whole observable outcome, at the drain's own
     * now: the row the journal appends and an operator reads. A
     * pass that did not complete writes no row.
     */
    public function recordDrainOutcome(DrainTrace $drainTrace): void
    {
        $this->connection->execute(
            QueryBuilder::table(
                $this->pdoDeliveryTables->journalTable()->name,
                null,
                $this->identifierQuoting,
            )
                ->insertRows([
                    [
                        'record_id' => DeliveryEntryId::mint()->spelling(),
                        'record' => $this->drainTraceEncoder->encode($drainTrace),
                    ],
                ])
                ->build(),
        );
    }
}
