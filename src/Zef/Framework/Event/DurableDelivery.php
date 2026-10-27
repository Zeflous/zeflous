<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The durable regime's composition root: the folds and books of
 * the durable arm, built at construction and wired together over
 * the store and the resolver the host spells, so a host that
 * spells the policy and the persistence gets the whole regime in
 * one object. The mirror of the in-process {@see AsyncDelivery},
 * with the persistence question answered by a port instead of an
 * in-process book.
 *
 * The regime owns no clock and sleeps never (KDD-EA4, carried
 * over): "now" arrives as the drain's own parameter. Its failure
 * discipline is per-delivery (KDD-EA2, carried over), judged by
 * the SAME {@see DeliveryFailure} fold the in-process arm judges
 * with -- one arithmetic, two books. The quarantine and the
 * dead-letter book this engine keeps are process-local by
 * declaration (the durable answers are the named follow-ups the
 * decision record spells); the store's own dead-letter records are
 * the durable story an operator reads.
 */
final readonly class DurableDelivery
{
    private DeadLetterBook $deadLetterBook;

    private ListenerQuarantine $listenerQuarantine;

    private DurableAdmission $durableAdmission;

    private DurableDrainPass $durableDrainPass;

    /**
     * @throws InvalidArgumentException when the quarantine
     *                                  threshold is below one -- a threshold of zero would
     *                                  quarantine a listener on no dead letter at all
     */
    public function __construct(
        private DeferredDeliveryStore $deferredDeliveryStore,
        EventListenerResolver $eventListenerResolver,
        EventBook $eventBook,
        RetryPolicy $retryPolicy,
        int $quarantineThreshold,
    ) {
        if ($quarantineThreshold < 1) {
            throw new InvalidArgumentException(
                'A quarantine threshold is at least one dead letter, got '
                . $quarantineThreshold . '.',
            );
        }

        $this->deadLetterBook = new DeadLetterBook();
        $this->listenerQuarantine = new ListenerQuarantine();
        $this->durableAdmission = new DurableAdmission(
            $this->deferredDeliveryStore,
            $this->listenerQuarantine,
        );
        $durableSettlement = new DurableSettlement($this->deferredDeliveryStore);
        $this->durableDrainPass = new DurableDrainPass(
            $this->deferredDeliveryStore,
            new DurableEntrySoundness($eventListenerResolver, $eventBook, $durableSettlement),
            new DeliveryAttempt(),
            new DeliveryFailure($retryPolicy, $this->deadLetterBook, $this->listenerQuarantine, $quarantineThreshold),
            new DurableVerdictAnswer($this->deadLetterBook, $this->listenerQuarantine, $durableSettlement),
            $durableSettlement,
        );
    }

    /**
     * Admits one prepared delivery durably -- the admission fold's
     * own judgment over the {@see PreparedDelivery} a plan prepared.
     */
    public function defer(PreparedDelivery $preparedDelivery): DeferralOutcome
    {
        return $this->durableAdmission->admit($preparedDelivery);
    }

    /**
     * One drain pass over the store's due entries at the given now
     * -- the {@see DurableDrainPass} fold's own run.
     */
    public function drain(int $now): DrainOutcome
    {
        return $this->durableDrainPass->run($now);
    }

    /**
     * The dead letters this process's drains wrote, in drain order
     * -- the in-process inspection seam over the failure record.
     * The durable story lives in the store's own dead-letter
     * records; this book is the judgment's attribution state.
     *
     * @return list<DeadLetter>
     */
    public function deadLetters(): array
    {
        return $this->deadLetterBook->all();
    }

    /**
     * Whether the listener is quarantined -- the admission filter
     * the next defer will read. Process-local by declaration: a
     * fresh process starts quarantine-free and re-learns by
     * attributing fresh letters.
     */
    public function isListenerQuarantined(EventListener $eventListener): bool
    {
        return $this->listenerQuarantine->isQuarantined($eventListener);
    }

    /**
     * How many entries the store still holds -- admitted,
     * not-yet-due, or taken-but-not-settled alike -- the engine's
     * seam over the persistence the host spelled.
     */
    public function pendingCount(): int
    {
        return $this->deferredDeliveryStore->pendingCount();
    }
}
