<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use Throwable;

/**
 * The failure regime's judgment fold: what one failed delivery
 * becomes -- the retry entry with its backoff ready-at, or the dead
 * letter (with the quarantine verdict the listener's attribution
 * earned). This is the fold the drain's own difficulty ceiling forced
 * out: judging a failure and steering the pass over the due entries
 * are two questions, two seams.
 *
 * The judgment owns the arithmetic and the attribution read; the
 * drain owns the writes (the requeue, the append, the quarantine act)
 * and the counts. The attribution counts the letter the judgment is
 * about to earn the drain -- the threshold asks whether THIS death
 * crosses it, so a listener at threshold-minus-one quarantines on the
 * death that arrives, never one earlier.
 */
final readonly class DeliveryFailure
{
    public function __construct(
        private RetryPolicy $retryPolicy,
        private DeadLetterBook $deadLetterBook,
        private ListenerQuarantine $listenerQuarantine,
        private int $quarantineThreshold,
    ) {
    }

    /**
     * Judges one failed delivery at the drain's own now: a retry when
     * the policy still permits an attempt (the backoff answered for
     * the attempt that just failed), otherwise the dead letter and
     * the quarantine verdict the listener's attribution earned.
     */
    public function resolve(DeferredDelivery $deferredDelivery, Throwable $throwable, int $now): FailureVerdict
    {
        $attempt = $deferredDelivery->attempt();

        if ($this->retryPolicy->permitsAnotherAttempt($attempt)) {
            return FailureVerdict::retry($deferredDelivery->withNextAttempt(
                $attempt + 1,
                $this->retryPolicy->readyAtForNextAttempt($attempt, $now),
            ));
        }

        $eventListener = $deferredDelivery->listener();

        return FailureVerdict::death(
            new DeadLetter(
                $deferredDelivery->event()->name(),
                $eventListener,
                $attempt,
                $throwable::class,
                $throwable->getMessage(),
                $now,
            ),
            !$this->listenerQuarantine->isQuarantined($eventListener)
                && $this->deadLetterBook->attributedTo($eventListener) + 1 >= $this->quarantineThreshold,
        );
    }
}
