<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use Throwable;

/**
 * The durable arm's drain pass fold: one pass over the store's due
 * entries at the given now -- the mirror of the in-process
 * {@see DrainPass}, over the vocabulary that crossed a process
 * boundary. Each due entry is first judged sound through the
 * {@see DurableEntrySoundness} fold (resolved to a listener,
 * rebuilt and re-validated against the live definitions -- an
 * unsound entry's story is already recorded and settled there);
 * a sound entry is then attempted and judged by the SAME folds
 * the in-process arm uses, and every answered entry leaves the
 * store through the {@see DurableSettlement} fold's
 * write-the-answer-then-settle-the-question ordering -- the
 * verdict's own writes composed by {@see DurableVerdictAnswer}.
 *
 * The arithmetic is byte-identical to the in-process regime by
 * construction -- one {@see DeliveryAttempt} boundary, one
 * {@see DeliveryFailure} judgment, one attribution book and one
 * quarantine, over the {@see DeferredDelivery} the soundness fold
 * composed from the record and the resolved listener. The books
 * are the STORE's own (the identity-keyed re-spelling the
 * process-local arm keeps in memory): the verdict-answer fold
 * attributes each death, reads the threshold's crossing from the
 * book that holds it, and records the containment the crossing
 * earned, answering whether the containment is newly this pass's
 * to count. Unsound letters (the unresolved identity, the stale
 * shape) are NOT attributed to a listener (the definition moved,
 * the service retired -- neither is the listener's death to
 * answer for), so they never cross the quarantine threshold.
 *
 * The pass's own last act is its trace: the five counts it
 * answered with, at the now it was drained at, journalled through
 * the {@see DurableDrainJournal} fold (KDD-EA12's seam -- the
 * counts are the traceability). A pass that did not complete
 * writes no trace; a completed pass's trace is the record an
 * operator reads the regime's history from.
 */
final readonly class DurableDrainPass
{
    public function __construct(
        private DeferredDeliveryStore $deferredDeliveryStore,
        private DurableEntrySoundness $durableEntrySoundness,
        private DeliveryAttempt $deliveryAttempt,
        private DeliveryFailure $deliveryFailure,
        private DurableVerdictAnswer $durableVerdictAnswer,
        private DurableSettlement $durableSettlement,
        private DurableDrainJournal $durableDrainJournal,
    ) {
    }

    /**
     * One drain pass over the store's due entries at the given
     * now: judges each entry sound, delivers the sound ones in
     * their own failure regimes, requeues the retries with their
     * backoff ready-at, records and settles the spent, quarantines
     * the listener whose attributed deaths just reached the
     * threshold, and journals the pass's five counts at its own
     * now. Entries saved during the pass are next-pass material --
     * the due list is the store's own snapshot, so no self-feeding
     * loop can keep a drain alive.
     */
    public function run(int $now): DrainOutcome
    {
        $delivered = 0;
        $retried = 0;
        $deadLettered = 0;
        $quarantined = 0;

        foreach ($this->deferredDeliveryStore->takeDue($now) as $deliveryEnvelope) {
            $deferredDelivery = $this->durableEntrySoundness->sound($deliveryEnvelope, $now);

            if (!$deferredDelivery instanceof DeferredDelivery) {
                ++$deadLettered;

                continue;
            }

            $deliveryFailure = $this->deliveryAttempt->run($deferredDelivery);

            if (!$deliveryFailure instanceof Throwable) {
                $this->durableSettlement->settle($deliveryEnvelope);
                ++$delivered;

                continue;
            }

            $failureVerdict = $this->deliveryFailure->resolve($deferredDelivery, $deliveryFailure, $now);
            $newlyQuarantined = $this->durableVerdictAnswer->answer(
                $deliveryEnvelope,
                $failureVerdict,
                $deliveryFailure,
                $now,
            );

            if ($failureVerdict->isRetry()) {
                ++$retried;

                continue;
            }

            ++$deadLettered;

            if (!$newlyQuarantined) {
                continue;
            }

            ++$quarantined;
        }

        return $this->durableDrainJournal->journal($now, $delivered, $retried, $deadLettered, $quarantined);
    }
}
