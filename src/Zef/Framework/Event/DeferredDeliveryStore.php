<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The durable arm's persistence port: the question a host asks in
 * one sentence -- "hold these entries, answer the due ones, forget
 * the settled" -- spelled as the six verbs every durable store must
 * answer. The Event lane owns the QUESTION and stays
 * dependency-free (the in-process posture every fold here
 * carries); the ANSWER is an adapter's own business, and the first
 * one lives in the Adapters lane over the local filesystem --
 * {@see \Zef\Framework\Adapters\Event\FileDeliveryStore} -- with
 * the Redis and PDO answers the roadmap's cross-process phase
 * names as its own follow-ups over this same port.
 *
 * The protocol is at-least-once, by declaration: {@see takeDue()}
 * READS (an entry taken but not yet settled is re-taken by the
 * next pass -- a crash between taking and answering can never
 * lose an entry, only redeliver it), every drain writes the
 * ANSWER first and settles the question second (a retry is
 * requeued before the spent entry settles, a dead letter recorded
 * before the spent entry settles -- the crash between the two
 * costs a duplicate, never a loss), and {@see settle()} is
 * idempotent (settling a settled entry is the no-op the
 * redelivery arithmetic already assumes). Listeners own their
 * idempotency; the store owns the survival.
 *
 * {@see save()} is the admission arm and carries the
 * backpressure: a batch that would push the store past its
 * capacity is refused WHOLE, the counts spelled -- the in-process
 * queue's own doctrine, spelled by the store that can actually
 * enforce it. {@see requeue()} is the retry arm and is not an
 * admission: the in-process queue's own admit/requeue split
 * mirrored, so a queue draining at capacity can still retry the
 * work it already holds. {@see deadLetter()} records the spent
 * entry's whole story where an operator can read it -- the
 * roadmap's dead-letter queue demand, answered as a record the
 * durable store owns rather than a silent loss.
 */
interface DeferredDeliveryStore
{
    /**
     * Admits a batch of entries as one unit. The capacity refusal
     * is the backpressure the boundary owns: the whole batch is
     * refused with the counts spelled, and draining is the host's
     * job.
     *
     * @param DeliveryEnvelope ...$envelopes the entries one durable defer produced
     *
     * @throws InvalidArgumentException when the batch would push the
     *                                  store past its capacity, or the store cannot hold it whole
     */
    public function save(DeliveryEnvelope ...$envelopes): void;

    /**
     * Writes one retry entry -- the answer a failed delivery
     * became. Not an admission: no capacity is demanded, because
     * the work the retry continues is work the store already
     * answered for.
     *
     * @throws InvalidArgumentException when the store cannot hold the entry
     */
    public function requeue(DeliveryEnvelope $deliveryEnvelope): void;

    /**
     * The entries whose ready-at has arrived at the given now,
     * ordered by ready-at and then entry identity -- a READ, never
     * a removal: the entries stay until they are settled, which is
     * what makes a crash between taking and answering a
     * redelivery instead of a loss.
     *
     * @return list<DeliveryEnvelope>
     */
    public function takeDue(int $now): array;

    /**
     * Finishes the store's business with the entries -- delivered,
     * retried under a fresh identity, or dead-lettered. Idempotent:
     * settling a settled entry is the no-op the at-least-once
     * doctrine already assumes.
     *
     * @param DeliveryEnvelope ...$envelopes the spent entries
     *
     * @throws InvalidArgumentException when an entry cannot be
     *                                  removed from the store
     */
    public function settle(DeliveryEnvelope ...$envelopes): void;

    /**
     * Records the spent entry's whole story where an operator can
     * read it: the entry's own record, the failure's class and
     * message, and the dead-at moment. Keyed by the entry's own
     * identity -- the same entry dead-lettered twice (the
     * at-least-once duplicate) rewrites the same story, never
     * doubles it.
     *
     * @throws InvalidArgumentException when the record cannot be
     *                                  written
     */
    public function deadLetter(
        DeliveryEnvelope $deliveryEnvelope,
        string $failureClass,
        string $failureMessage,
        int $deadAt,
    ): void;

    /**
     * How many entries the store still holds -- admitted,
     * not-yet-due, or taken-but-not-settled alike.
     */
    public function pendingCount(): int;
}
