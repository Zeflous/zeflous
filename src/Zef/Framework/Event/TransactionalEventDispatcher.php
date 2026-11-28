<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;
use Random\RandomException;

/**
 * The transactional vocabulary: the defer/relay pair over the
 * outbox the host spells, composed one-way with the durable arm
 * the relay feeds -- the mirror of {@see DurableEventDispatcher},
 * for the events whose emission must be atomic with the host's
 * own business write. A host asks its dispatcher for this facade
 * with {@see EventDispatcher::transactionalDelivery()}; a
 * dispatcher that was never asked simply has no transactional
 * verbs to call, exactly as the in-process and durable arms spell
 * their own one-way compositions.
 *
 * THE DEFER IS THE SAME-TRANSACTION WRITE. It validates the
 * payload against the live definition at the delivery boundary
 * (KDD-EA10: the producer's mistake surfaces at the producer's
 * call -- a payload the definition does not answer refuses the
 * call, and a host appending inside its own transaction reads the
 * refusal as the rollback it is), then appends ONE outbox row
 * riding whatever scope the caller carries over the outbox's
 * connection -- the store opens no scope of its own, so the
 * business write and the row land together or neither lands: the
 * event is never emitted for a change that never happened, the
 * Fase-4 promise the ROADMAP spells. The pool snapshot is
 * deliberately NOT taken here: the relay's defer owns it
 * (KDD-EA3 read from the relay side -- the subscription state
 * live when the relay runs IS the message), so a listener
 * subscribed between append and relay joins, exactly as the
 * durable arm's own drain re-validation reads the live
 * definitions (DU11).
 *
 * THE RELAY IS THE EXACTLY-ONCE HANDOFF. One pass reads the
 * pending rows, defers each into the durable arm (re-validating
 * against the definition live at the relay), and removes the
 * relayed rows -- the read, the defers and the removals one unit
 * the outbox store answers both-or-neither, so no row enters the
 * delivery store twice from the outbox side and no relayed row is
 * ever lost: the exactly-once answer KDD-DU1 defers to this lane.
 * The delivery entries then drain under the durable arm's own
 * at-least-once doctrine -- listeners own their idempotency, the
 * store owns the survival, and the transactional lane owns the
 * emission atomicity alone.
 */
final readonly class TransactionalEventDispatcher
{
    public function __construct(
        private EventBook $eventBook,
        private OutboxStore $outboxStore,
        private DurableEventDispatcher $durableEventDispatcher,
    ) {
    }

    /**
     * Defers an event transactionally: validates the payload
     * against the name's own live shape (the producer's mistake
     * surfaces at the producer's call -- KDD-EA10) and appends one
     * outbox row riding the caller's own scope -- the
     * same-transaction write this arm exists for.
     *
     * @param array<array-key, mixed> $values the producer's payload
     * @param int                     $now    the append's own moment, the host's
     *                                        clock spelled at the call (the regime owns no clock,
     *                                        KDD-EA4 carried): the relay's read order
     *
     * @return OutboxMessage the row the defer appended -- its
     *                       identity the host's own journaling may keep
     *
     * @throws InvalidArgumentException when the event is not
     *                                  defined, the payload does not answer its shape, a value
     *                                  cannot survive the serialized spelling, or the outbox
     *                                  refuses the append (the capacity backpressure)
     * @throws RandomException          when the engine's random source
     *                                  refuses to answer the row's identity
     */
    public function defer(EventName $eventName, array $values, int $now): OutboxMessage
    {
        $eventPayload = $this->eventBook->shapeFor($eventName)->validate($values);

        $outboxMessage = new OutboxMessage(
            OutboxMessageId::mint(),
            $eventName,
            $eventPayload->values(),
            $now,
        );

        $this->outboxStore->append($outboxMessage);

        return $outboxMessage;
    }

    /**
     * One relay pass over the outbox's pending rows: the read, the
     * defers into the durable arm and the removals one unit, the
     * pass's counts the operator reads. A refused defer (the
     * delivery store's capacity backpressure, a shape the live
     * definitions no longer answer) rolls the whole pass back and
     * every row stays pending -- the next pass re-reads them.
     *
     * @param int $limit the most rows one pass relays (>= 1)
     *
     * @return OutboxRelayOutcome the pass's own report -- the rows
     *                            relayed, the entries admitted, the listeners the
     *                            quarantine skipped
     *
     * @throws InvalidArgumentException when the limit is below one,
     *                                  or the engine refuses any verb the pass rides
     */
    public function relay(int $limit): OutboxRelayOutcome
    {
        $admitted = 0;
        $skippedQuarantined = 0;

        $relayed = $this->outboxStore->relay(
            $limit,
            function (OutboxMessage ...$outboxMessages) use (&$admitted, &$skippedQuarantined): void {
                foreach ($outboxMessages as $outboxMessage) {
                    $deferralOutcome = $this->durableEventDispatcher->defer(
                        $outboxMessage->name(),
                        $outboxMessage->values(),
                    );

                    $admitted += $deferralOutcome->admitted();
                    $skippedQuarantined += $deferralOutcome->skippedQuarantined();
                }
            },
        );

        return new OutboxRelayOutcome($relayed, $admitted, $skippedQuarantined);
    }

    /**
     * How many rows the outbox still holds -- every appended row
     * the relay has not removed yet, the operator's count spelled
     * over the outbox the host handed this facade ({@see
     * DurableEventDispatcher::pendingCount()}, the same mirror the
     * durable facade spells).
     */
    public function pendingCount(): int
    {
        return $this->outboxStore->pendingCount();
    }
}
