<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use Throwable;

/**
 * The durable drain's verdict-answer fold: what the judgment's
 * verdict becomes in the store and the books -- a retry verdict
 * requeues the fresh entry before the spent one settles (the
 * settlement ordering), a death verdict records the whole story
 * before the spent one settles, appends the in-process dead-letter
 * book the attribution reads, and performs the quarantine act the
 * verdict earned. The fold composes the writes; the counts are the
 * pass's own answer, read from the verdict itself.
 */
final readonly class DurableVerdictAnswer
{
    public function __construct(
        private DeadLetterBook $deadLetterBook,
        private ListenerQuarantine $listenerQuarantine,
        private DurableSettlement $durableSettlement,
    ) {
    }

    /**
     * Answers one judged failure: the retry's write-then-settle, or
     * the death's record-then-settle with the book append and the
     * quarantine act.
     */
    public function answer(
        DeliveryEnvelope $deliveryEnvelope,
        DeferredDelivery $deferredDelivery,
        FailureVerdict $failureVerdict,
        Throwable $throwable,
        int $now,
    ): void {
        if ($failureVerdict->isRetry()) {
            $retryEntry = $failureVerdict->retryEntry();
            $this->durableSettlement->retry(
                $deliveryEnvelope,
                $deliveryEnvelope->withNextAttempt($retryEntry->attempt(), $retryEntry->readyAt()),
            );

            return;
        }

        $this->durableSettlement->dead(
            $deliveryEnvelope,
            $throwable::class,
            $throwable->getMessage(),
            $now,
        );
        $this->deadLetterBook->append($failureVerdict->deadLetter());

        if (!$failureVerdict->quarantines()) {
            return;
        }

        $this->listenerQuarantine->quarantine($deferredDelivery->listener());
    }
}
