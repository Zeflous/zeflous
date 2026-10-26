<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The admission fold: what one defer admits -- the pool snapshot
 * filtered by the quarantine, one pending entry per surviving
 * listener in pool order, bounded by the queue's capacity. This is
 * the fold the engine's own difficulty ceiling forced out: judging
 * admission and steering the drain pass are two questions, two
 * seams.
 *
 * The pool is the dispatcher's own snapshot (KDD-EA3) -- this fold
 * never re-reads the subscription state, so a late subscriber cannot
 * join and an unsubscribe cannot retract. The quarantine filter is
 * forward-looking containment (KDD-EA7): the listeners the regime has
 * given up on are skipped, never silently -- the outcome names the
 * count.
 */
final readonly class DeliveryAdmission
{
    public function __construct(
        private AsyncDeliveryQueue $asyncDeliveryQueue,
        private ListenerQuarantine $listenerQuarantine,
    ) {
    }

    /**
     * Admits one prepared delivery and answers the outcome.
     */
    public function admit(PreparedDelivery $preparedDelivery): DeferralOutcome
    {
        $entries = [];
        $skippedQuarantined = 0;

        foreach ($preparedDelivery->pool() as $listenerSubscription) {
            $eventListener = $listenerSubscription->listener();

            if ($this->listenerQuarantine->isQuarantined($eventListener)) {
                ++$skippedQuarantined;

                continue;
            }

            $entries[] = new DeferredDelivery($preparedDelivery->event(), $eventListener, 1, 0);
        }

        $this->asyncDeliveryQueue->admit($entries);

        return new DeferralOutcome($preparedDelivery->event()->name(), \count($entries), $skippedQuarantined);
    }
}
