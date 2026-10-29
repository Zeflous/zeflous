<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use Throwable;

/**
 * The failure regime's judgment fold: what one failed delivery
 * becomes -- the retry entry with its backoff ready-at, or the dead
 * letter it earned. This is the fold the drain's own difficulty
 * ceiling forced out: judging a failure and steering the pass over
 * the due entries are two questions, two seams.
 *
 * The judgment owns the ARITHMETIC and nothing else -- the policy's
 * retry-versus-death answer and the letter's own story -- because
 * the quarantine crossing is the BOOK's own read, taken after the
 * letter is appended (or attributed): a judgment that read the
 * books before writing to them would guess at state it had not yet
 * spelled. Both arms compose this one fold verbatim -- one
 * arithmetic, two books -- and each book answers the crossing for
 * the key it is spelled by: the object in-process, the identity
 * across the boundary.
 */
final readonly class DeliveryFailure
{
    public function __construct(private RetryPolicy $retryPolicy)
    {
    }

    /**
     * Judges one failed delivery at the drain's own now: a retry when
     * the policy still permits an attempt (the backoff answered for
     * the attempt that just failed), otherwise the dead letter the
     * delivery earned.
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

        return FailureVerdict::death(
            new DeadLetter(
                $deferredDelivery->event()->name(),
                $deferredDelivery->listener(),
                $attempt,
                $throwable::class,
                $throwable->getMessage(),
                $now,
            ),
        );
    }
}
