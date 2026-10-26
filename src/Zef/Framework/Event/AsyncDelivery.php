<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;
use Throwable;

/**
 * The deferred regime's orchestrator: admission (the pool snapshot
 * the dispatcher hands in, filtered by the quarantine, bounded by
 * the queue's capacity) and the drain fold (the one pass over the
 * due entries KDD-EA9 spells). Every book the regime keeps -- the
 * pending queue, the dead letters, the quarantine -- lives here,
 * built at construction, so a host that spells the policy gets the
 * whole regime in one object.
 *
 * The fold's failure discipline is per-delivery (KDD-EA2): a
 * listener's exception is caught at its own entry, answered by the
 * retry arithmetic or the dead-letter book, and never stops the
 * entries behind it. The fold owns no clock and sleeps never --
 * "now" arrives as the drain's own parameter (KDD-EA4).
 */
final readonly class AsyncDelivery
{
    private AsyncDeliveryQueue $asyncDeliveryQueue;

    private DeadLetterBook $deadLetterBook;

    private ListenerQuarantine $listenerQuarantine;

    /**
     * @throws InvalidArgumentException when the quarantine
     *                                  threshold is below one -- a threshold of zero would
     *                                  quarantine a listener on no dead letter at all
     */
    public function __construct(
        private RetryPolicy $retryPolicy,
        int $capacity,
        private int $quarantineThreshold,
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
    }

    /**
     * Admits one deferred event: one pending entry per pool
     * listener the quarantine has not given up on, in pool order.
     * The pool is the dispatcher's own snapshot (KDD-EA3) -- this
     * fold never re-reads the subscription state, so a late
     * subscriber cannot join and an unsubscribe cannot retract.
     *
     * @param list<ListenerSubscription> $pool the dispatch-time delivery pool
     */
    public function defer(Event $event, array $pool): DeferralOutcome
    {
        $entries = [];
        $skippedQuarantined = 0;

        foreach ($pool as $subscription) {
            $eventListener = $subscription->listener();

            if ($this->listenerQuarantine->isQuarantined($eventListener)) {
                ++$skippedQuarantined;

                continue;
            }

            $entries[] = new DeferredDelivery($event, $eventListener, 1, 0);
        }

        $this->asyncDeliveryQueue->admit($entries);

        return new DeferralOutcome($event->name(), \count($entries), $skippedQuarantined);
    }

    /**
     * One drain pass at the given now: delivers the due entries in
     * queue order, each in its own failure regime; requeues the
     * retries with their backoff ready-at; dead-letters the
     * exhausted; quarantines the listener whose attributed letters
     * just reached the threshold. Entries deferred during the pass
     * are next-pass material -- the due list is a snapshot, so no
     * self-feeding loop can keep a drain alive.
     */
    public function drain(int $now): DrainOutcome
    {
        $delivered = 0;
        $retried = 0;
        $deadLettered = 0;
        $quarantined = 0;

        foreach ($this->asyncDeliveryQueue->takeDue($now) as $deferredDelivery) {
            $eventListener = $deferredDelivery->listener();

            try {
                $eventListener->handle($deferredDelivery->event());
                ++$delivered;
            } catch (Throwable $deliveryFailure) {
                $attempt = $deferredDelivery->attempt();

                if ($this->retryPolicy->permitsAnotherAttempt($attempt)) {
                    $this->asyncDeliveryQueue->requeue($deferredDelivery->withNextAttempt(
                        $attempt + 1,
                        $this->retryPolicy->readyAtForNextAttempt($attempt, $now),
                    ));
                    ++$retried;

                    continue;
                }

                $this->deadLetterBook->append(new DeadLetter(
                    $deferredDelivery->event()->name(),
                    $eventListener,
                    $attempt,
                    $deliveryFailure::class,
                    $deliveryFailure->getMessage(),
                    $now,
                ));
                ++$deadLettered;

                if (
                    !$this->listenerQuarantine->isQuarantined($eventListener)
                    && $this->deadLetterBook->attributedTo($eventListener) >= $this->quarantineThreshold
                ) {
                    $this->listenerQuarantine->quarantine($eventListener);
                    ++$quarantined;
                }
            }
        }

        return new DrainOutcome($delivered, $retried, $deadLettered, $quarantined, $this->asyncDeliveryQueue->count());
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
