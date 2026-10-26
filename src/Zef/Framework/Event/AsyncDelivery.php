<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The deferred regime's composition root: the folds and books of
 * the regime, built at construction and wired together, so a host
 * that spells the policy gets the whole regime in one object. The
 * regime's own behaviour lives in its folds -- the
 * {@see DeliveryAdmission} fold for what a defer admits, the
 * {@see DrainPass} fold for what a drain pass does -- and this
 * class stays the engine that composes them.
 *
 * The regime owns no clock and sleeps never (KDD-EA4): "now"
 * arrives as the drain's own parameter. Its failure discipline is
 * per-delivery (KDD-EA2), judged by the {@see DeliveryFailure}
 * fold.
 */
final readonly class AsyncDelivery
{
    private AsyncDeliveryQueue $asyncDeliveryQueue;

    private DeadLetterBook $deadLetterBook;

    private ListenerQuarantine $listenerQuarantine;

    private DeliveryAdmission $deliveryAdmission;

    private DrainPass $drainPass;

    /**
     * @throws InvalidArgumentException when the quarantine
     *                                  threshold is below one -- a threshold of zero would
     *                                  quarantine a listener on no dead letter at all
     */
    public function __construct(
        RetryPolicy $retryPolicy,
        int $capacity,
        int $quarantineThreshold,
    ) {
        if ($quarantineThreshold < 1) {
            throw new InvalidArgumentException(
                'A quarantine threshold is at least one dead letter, got '
                . $quarantineThreshold . '.',
            );
        }

        $this->asyncDeliveryQueue = new AsyncDeliveryQueue($capacity);
        $this->deadLetterBook = new DeadLetterBook();
        $this->listenerQuarantine = new ListenerQuarantine();
        $this->deliveryAdmission = new DeliveryAdmission($this->asyncDeliveryQueue, $this->listenerQuarantine);
        $this->drainPass = new DrainPass(
            $this->asyncDeliveryQueue,
            new DeliveryAttempt(),
            new DeliveryFailure($retryPolicy, $this->deadLetterBook, $this->listenerQuarantine, $quarantineThreshold),
            $this->deadLetterBook,
            $this->listenerQuarantine,
        );
    }

    /**
     * Admits one prepared delivery -- the admission fold's own
     * judgment over the {@see PreparedDelivery} a plan prepared.
     */
    public function defer(PreparedDelivery $preparedDelivery): DeferralOutcome
    {
        return $this->deliveryAdmission->admit($preparedDelivery);
    }

    /**
     * One drain pass at the given now -- the {@see DrainPass} fold's
     * own run.
     */
    public function drain(int $now): DrainOutcome
    {
        return $this->drainPass->run($now);
    }

    /**
     * The dead letters written so far, in drain order -- the host's
     * inspection seam over the failure record.
     *
     * @return list<DeadLetter>
     */
    public function deadLetters(): array
    {
        return $this->deadLetterBook->all();
    }

    /**
     * Whether the listener is quarantined -- the admission filter
     * the next defer will read.
     */
    public function isListenerQuarantined(EventListener $eventListener): bool
    {
        return $this->listenerQuarantine->isQuarantined($eventListener);
    }

    /**
     * How many entries the queue still holds -- admitted,
     * not-yet-due, or requeued.
     */
    public function pendingCount(): int
    {
        return $this->asyncDeliveryQueue->count();
    }
}
