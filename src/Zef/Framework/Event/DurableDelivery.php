<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The durable regime's composition root: the folds of the durable
 * arm, built at construction and wired together over the store and
 * the resolver the host spells, so a host that spells the policy
 * and the persistence gets the whole regime in one object. The
 * mirror of the in-process {@see AsyncDelivery}, with the
 * persistence question answered by a port instead of an in-process
 * book -- and with the regime's BOOKS spelled by the same store:
 * the attribution and the quarantine the in-process arm keeps in
 * memory are re-spelled over the identity that survives the
 * boundary, so the quarantine a previous process earned is the
 * quarantine a fresh process starts with.
 *
 * The regime owns no clock and sleeps never (KDD-EA4, carried
 * over): "now" arrives as the drain's own parameter. Its failure
 * discipline is per-delivery (KDD-EA2, carried over), judged by
 * the SAME {@see DeliveryFailure} fold the in-process arm judges
 * with -- one arithmetic, two books. The durable story an operator
 * reads lives in the store's own records: the dead-letter lane,
 * the attribution lane, the quarantine lane and the drain journal
 * are the store's own answer, never an in-process echo.
 */
final readonly class DurableDelivery
{
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

        $this->durableAdmission = new DurableAdmission($this->deferredDeliveryStore);
        $durableSettlement = new DurableSettlement($this->deferredDeliveryStore);
        $this->durableDrainPass = new DurableDrainPass(
            $this->deferredDeliveryStore,
            new DurableEntrySoundness($eventListenerResolver, $eventBook, $durableSettlement),
            new DeliveryAttempt(),
            new DeliveryFailure($retryPolicy),
            new DurableVerdictAnswer($this->deferredDeliveryStore, $durableSettlement, $quarantineThreshold),
            $durableSettlement,
            new DurableDrainJournal($this->deferredDeliveryStore),
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
     * -- the {@see DurableDrainPass} fold's own run, journalling
     * the pass's trace as its own last act.
     */
    public function drain(int $now): DrainOutcome
    {
        return $this->durableDrainPass->run($now);
    }

    /**
     * How many attributed deaths the store holds for the listener's
     * identity -- the count the quarantine threshold reads, the
     * durable re-spelling of the in-process book's attribution. The
     * dead letters' whole stories live in the store's own
     * dead-letter records; this count is the book the threshold
     * reads.
     */
    public function deadLettersAttributedTo(ListenerIdentity $listenerIdentity): int
    {
        return $this->deferredDeliveryStore->deadLettersAttributedTo($listenerIdentity);
    }

    /**
     * Whether the listener's identity is quarantined -- the filter
     * the next defer will read, spelled by the store: the quarantine
     * a previous process earned survives the boundary, and a fresh
     * process reads the store's own answer.
     */
    public function isListenerQuarantined(ListenerIdentity $listenerIdentity): bool
    {
        return $this->deferredDeliveryStore->isListenerQuarantined($listenerIdentity);
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
