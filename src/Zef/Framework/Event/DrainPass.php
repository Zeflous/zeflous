<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use Throwable;

/**
 * The drain pass fold: one pass over the due entries at the given
 * now -- the pass KDD-EA9 spells. This is the fold the engine's own
 * difficulty ceiling forced out: steering the pass and owning the
 * regime's books are two questions, two seams.
 *
 * The pass owns no clock (KDD-EA4): "now" is its parameter. The
 * failure discipline is per-delivery (KDD-EA2): the
 * {@see DeliveryAttempt} boundary wraps one entry's listener call,
 * the {@see DeliveryFailure} fold judges what the failure becomes,
 * and neither ever stops the entries behind.
 */
final readonly class DrainPass
{
    public function __construct(
        private AsyncDeliveryQueue $asyncDeliveryQueue,
        private DeliveryAttempt $deliveryAttempt,
        private DeliveryFailure $deliveryFailure,
        private DeadLetterBook $deadLetterBook,
        private ListenerQuarantine $listenerQuarantine,
    ) {
    }

    /**
     * One drain pass: delivers the due entries in queue order,
     * requeues the retries with their backoff ready-at,
     * dead-letters the exhausted, quarantines the listener whose
     * attributed letters just reached the threshold. Entries
     * deferred during the pass are next-pass material -- the due
     * list is a snapshot, so no self-feeding loop can keep a drain
     * alive.
     */
    public function run(int $now): DrainOutcome
    {
        $delivered = 0;
        $retried = 0;
        $deadLettered = 0;
        $quarantined = 0;

        foreach ($this->asyncDeliveryQueue->takeDue($now) as $deferredDelivery) {
            $deliveryFailure = $this->deliveryAttempt->run($deferredDelivery);

            if (!$deliveryFailure instanceof Throwable) {
                ++$delivered;

                continue;
            }

            $verdict = $this->deliveryFailure->resolve($deferredDelivery, $deliveryFailure, $now);

            if ($verdict->isRetry()) {
                $this->asyncDeliveryQueue->requeue($verdict->retryEntry());
                ++$retried;

                continue;
            }

            $this->deadLetterBook->append($verdict->deadLetter());
            ++$deadLettered;

            if (!$verdict->quarantines()) {
                continue;
            }

            $this->listenerQuarantine->quarantine($deferredDelivery->listener());
            ++$quarantined;
        }

        return new DrainOutcome($delivered, $retried, $deadLettered, $quarantined, $this->asyncDeliveryQueue->count());
    }
}
