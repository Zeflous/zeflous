<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use Throwable;

/**
 * The durable drain's verdict-answer fold: what the judgment's
 * verdict becomes in the store and the books -- a retry verdict
 * requeues the fresh entry before the spent one settles (the
 * settlement ordering), a death verdict attributes the death to
 * the listener's IDENTITY first (the durable book's append,
 * idempotent per entry), reads the threshold's crossing from the
 * book that now HOLDS the letter, records the containment act the
 * crossing earned, and only then records the whole story and
 * settles the spent entry. The fold composes the writes; the
 * counts are the pass's own answer, read from the fold's own
 * return.
 *
 * The ordering is the at-least-once doctrine's own arithmetic: the
 * attribution, the quarantine act and the dead record are all
 * written BEFORE the spent entry settles, so a crash anywhere in
 * the window leaves an unsettled entry the next pass re-judges --
 * the attribution lands again as the idempotent no-op it is keyed
 * to be, the crossing is re-read from a book that already holds
 * the letter, and the containment that was already recorded is
 * answered as NOT newly earned. Every window costs a duplicate
 * redelivery, never a doubled book and never a lost story.
 *
 * The fold answers whether the death NEWLY quarantined the
 * listener -- the pass's quarantined count reads this answer, the
 * same crossing read the in-process pass takes from its own books
 * after the append.
 */
final readonly class DurableVerdictAnswer
{
    public function __construct(
        private DeferredDeliveryStore $deferredDeliveryStore,
        private DurableSettlement $durableSettlement,
        private int $quarantineThreshold,
    ) {
    }

    /**
     * Answers one judged failure: the retry's write-then-settle, or
     * the death's attribute-then-contain-then-record-then-settle.
     * Answers whether the death newly quarantined the listener.
     */
    public function answer(
        DeliveryEnvelope $deliveryEnvelope,
        FailureVerdict $failureVerdict,
        Throwable $throwable,
        int $now,
    ): bool {
        if ($failureVerdict->isRetry()) {
            $retryEntry = $failureVerdict->retryEntry();
            $this->durableSettlement->retry(
                $deliveryEnvelope,
                $deliveryEnvelope->withNextAttempt($retryEntry->attempt(), $retryEntry->readyAt()),
            );

            return false;
        }

        $listenerIdentity = $deliveryEnvelope->listenerIdentity();

        $this->deferredDeliveryStore->recordAttributedDeath(
            $deliveryEnvelope->entryId(),
            $listenerIdentity,
            $now,
        );

        $newlyQuarantined = !$this->deferredDeliveryStore->isListenerQuarantined($listenerIdentity)
            && $this->deferredDeliveryStore->deadLettersAttributedTo($listenerIdentity) >= $this->quarantineThreshold;

        if ($newlyQuarantined) {
            $this->deferredDeliveryStore->quarantineListener($listenerIdentity, $now);
        }

        $this->durableSettlement->dead(
            $deliveryEnvelope,
            $throwable::class,
            $throwable->getMessage(),
            $now,
        );

        return $newlyQuarantined;
    }
}
