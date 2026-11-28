<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The transactional arm's persistence port: the question the
 * transactional/outbox lane exists to answer -- "hold the events
 * my transaction produced, hand them to the durable arm when the
 * relay runs, forget the relayed" -- spelled as the verbs every
 * outbox store must answer. The Event lane owns the QUESTION and
 * stays dependency-free (the in-process posture every fold here
 * carries); the ANSWER is an adapter's own business, and the first
 * one lives in the Adapters lane over the database family's own
 * engine ({@see \Zef\Framework\Adapters\Event\PdoOutboxStore}) -- the same
 * port-then-adapter order the durable arm's store spelled first.
 *
 * THE SAME-TRANSACTION WRITE ({@see append()}). The row the host
 * appends rides the transaction the host's own scope carries: the
 * write joins whatever transaction is open over the same
 * connection the store composes, and the store NEVER opens a
 * scope of its own on the append -- the business write and the
 * outbox row land together or neither lands, the engine's own
 * atomicity the whole lane is named for. A rollback discards the
 * row the same rollback discards the business write: the event is
 * never emitted for a change that never happened, exactly the
 * Fase-4 promise the ROADMAP spells. An append outside any open
 * scope is the auto-commit posture the database family's own
 * standalone calls own: the row lands on its own, never silently
 * inside a scope the caller did not spell.
 *
 * THE RELAY ({@see relay()}). One relay pass is ONE unit over the
 * store's own connection: the pending rows read, the handoff's
 * writes and the relayed rows' removal BOTH-OR-NEITHER -- a
 * refused handoff (the delivery store's capacity backpressure,
 * a shape the live definitions no longer answer) rolls the whole
 * pass back and every row stays pending, so the next pass
 * re-reads them, and a crash mid-pass costs the same rollback the
 * engine's own statement boundary answers. THAT is the
 * exactly-once handoff KDD-DU1 defers here: no row enters the
 * delivery store twice from the outbox side, because no row is
 * removed while its delivery entries did not land. The relay
 * assumes it is the store's only relay -- the single-worker
 * doctrine the worker loop family spells; the cross-relay
 * exclusivity the delivery store's claim columns own is the drain
 * side's own question, answered downstream where the claims
 * already exist, and the deployment that runs many relays over
 * one outbox is the named follow-up's own consumer. The handoff
 * receives the messages in the read order and returns nothing:
 * its writes either succeed whole or refuse whole, and the
 * refusal is the rollback.
 *
 * {@see pendingCount()} answers the count the capacity arithmetic
 * reads -- the outbox's own pending bound spelled by the adapter
 * that can actually enforce it, the same doctrine the delivery
 * store's admission carries.
 */
interface OutboxStore
{
    /**
     * Appends the rows one transactional defer (or one batch of
     * them) produced, riding the transaction the caller's own
     * scope carries over the same connection -- the store opens no
     * scope of its own. A batch the store cannot hold whole
     * (capacity, a row the table already owns) is refused whole,
     * the counts spelled: the engine's statement atomicity the
     * same-transaction write is named for.
     *
     * @param OutboxMessage ...$outboxMessages the rows the host's
     *                                         transaction produced
     *
     * @throws InvalidArgumentException when the store cannot hold
     *                                  the batch whole
     */
    public function append(OutboxMessage ...$outboxMessages): void;

    /**
     * One relay pass as one unit: reads up to $limit pending rows
     * in the read order (appended-at then row identity, the DU9
     * ordering doctrine), hands them to the handoff inside the
     * pass's own transaction, and removes every handed row when
     * the handoff answered -- the handoff's writes and the
     * removals both-or-neither, the exactly-once handoff into the
     * durable arm. The handoff's refusal is the whole pass's
     * refusal: every row stays pending, the counts the next pass
     * re-reads.
     *
     * @param int                               $limit   the most rows one pass
     *                                                   relays (>= 1)
     * @param callable(OutboxMessage ...): void $handoff the fold that
     *                                                   writes each row's delivery entries into the
     *                                                   delivery store over the same connection -- the
     *                                                   relay's own composition answers it
     *
     * @return int how many rows the pass relayed
     *
     * @throws InvalidArgumentException when the limit is below one,
     *                                  or the engine refuses any verb the pass rides
     */
    public function relay(int $limit, callable $handoff): int;

    /**
     * How many rows the outbox still holds -- every appended row
     * the relay has not removed yet, the count the append's
     * capacity arithmetic reads.
     */
    public function pendingCount(): int;
}
