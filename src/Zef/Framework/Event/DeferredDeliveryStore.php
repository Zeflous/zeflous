<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The durable arm's persistence port: the question a host asks in
 * one sentence -- "hold these entries, answer the due ones, forget
 * the settled, keep the books, tell me what each pass answered" --
 * spelled as the verbs every durable store must answer. The Event
 * lane owns the QUESTION and stays dependency-free (the in-process
 * posture every fold here carries); the ANSWER is an adapter's own
 * business, and the first one lives in the Adapters lane over the
 * local filesystem -- {@see \Zef\Framework\Adapters\Event\FileDeliveryStore} -- with
 * the Redis and PDO answers the roadmap's cross-process phase names
 * as its own follow-ups over this same port.
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
 *
 * The store also owns the regime's BOOKS -- the attribution and
 * the quarantine the process-local arm keeps in memory, re-spelled
 * over the identity that survives the process boundary
 * (KDD-DU12's named follow-up): {@see recordAttributedDeath()}
 * attributes one spent entry's death to its listener's identity
 * (idempotent per entry -- the at-least-once redelivery of a
 * crashed pass re-attributes the same entry, never doubles the
 * book), {@see deadLettersAttributedTo()} answers the count the
 * quarantine threshold reads, {@see quarantineListener()} records
 * the containment act the threshold's crossing earned, and
 * {@see isListenerQuarantined()} answers the filter every future
 * defer reads -- a fresh process starts with the quarantine the
 * store already holds, never quarantine-free. Only the listener's
 * OWN failure is ever attributed (the unresolved-identity and
 * stale-shape causes are nobody's death to answer for), and that
 * doctrine stays the regime's own: the verbs are called by the
 * drain's verdict path alone. {@see recordDrainOutcome()} journals
 * one completed pass's five counts at the drain's own now -- the
 * traceability seam KDD-EA12 spells, the record an operator reads
 * the regime's history from.
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
     * Attributes one spent entry's death to the listener's identity
     * -- the durable re-spelling of the dead-letter book's append,
     * keyed by the entry's own identity so the at-least-once
     * redelivery of a crashed pass re-attributes the SAME entry
     * instead of doubling the book. The attribution timestamp is
     * the drain's own now (the regime owns no clock).
     *
     * @throws InvalidArgumentException when the attribution record
     *                                  cannot be written
     */
    public function recordAttributedDeath(
        DeliveryEntryId $deliveryEntryId,
        ListenerIdentity $listenerIdentity,
        int $attributedAt,
    ): void;

    /**
     * How many attributed deaths the store holds for the listener's
     * identity -- the count the quarantine threshold reads. Only
     * the listener's own failures are ever attributed here; the
     * unresolved-identity and stale-shape causes never cross the
     * threshold and are never the listener's death to answer for.
     */
    public function deadLettersAttributedTo(ListenerIdentity $listenerIdentity): int;

    /**
     * Records the containment act -- the identity whose attributed
     * deaths reached the threshold, whom every future defer will
     * skip. Idempotent: an already-quarantined identity is the
     * no-op the containment's own doctrine assumes, and restoring a
     * listener is the operator's own judgment (the record is the
     * operator's to remove, never the regime's to lift). The
     * quarantine timestamp is the drain's own now.
     *
     * @throws InvalidArgumentException when the quarantine record
     *                                  cannot be written
     */
    public function quarantineListener(
        ListenerIdentity $listenerIdentity,
        int $quarantinedAt,
    ): void;

    /**
     * Whether the listener's identity is quarantined -- the filter
     * every future defer reads, and the guard that keeps one
     * containment from being counted twice. A fresh process reads
     * the store's own answer: the quarantine a previous process
     * earned survives the boundary.
     */
    public function isListenerQuarantined(ListenerIdentity $listenerIdentity): bool;

    /**
     * How many entries the store still holds -- admitted,
     * not-yet-due, or taken-but-not-settled alike.
     */
    public function pendingCount(): int;

    /**
     * Journals one completed drain pass -- the five counts that are
     * the regime's whole observable outcome, at the drain's own
     * now: the traceability seam KDD-EA12 spells, answered as a
     * record the store appends and an operator reads. A pass that
     * did not complete writes no trace.
     *
     * @throws InvalidArgumentException when the trace record cannot
     *                                  be written
     */
    public function recordDrainOutcome(DrainTrace $drainTrace): void;
}
