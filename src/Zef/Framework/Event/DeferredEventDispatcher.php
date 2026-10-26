<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The deferred vocabulary: the defer/drain pair over a delivery plan
 * and the async regime's engine, composed one-way (ED11's doctrine:
 * every consumer reaches the layer one-way). A host asks its
 * dispatcher for this facade with the engine it wants -- a
 * dispatcher that was never asked simply has no deferred verbs to
 * call, which is the sync-only shape answered structurally instead
 * of by a refusal.
 */
final readonly class DeferredEventDispatcher
{
    public function __construct(
        private DeliveryPlan $deliveryPlan,
        private AsyncDelivery $asyncDelivery,
    ) {
    }

    /**
     * Defers an event: validates the payload at the delivery
     * boundary (the producer's mistake surfaces at the producer's
     * call -- KDD-EA10), freezes the event, snapshots the delivery
     * pool, and admits one pending entry per pool listener the
     * quarantine has not given up on. The snapshot is the message
     * (KDD-EA3): a listener subscribed after this call does not
     * receive the event, and an unsubscribe after it does not
     * retract it.
     *
     * @param array<array-key, mixed> $values the producer's payload
     *
     * @throws InvalidArgumentException when the event is not
     *                                  defined, the payload does not answer its shape, or the
     *                                  queue's capacity would be exceeded (the backpressure
     *                                  refusal -- drain before deferring more)
     */
    public function defer(EventName $eventName, array $values): DeferralOutcome
    {
        return $this->asyncDelivery->defer(
            $this->deliveryPlan->prepare($eventName, $values),
        );
    }

    /**
     * Runs one drain pass of the deferred regime at the given now:
     * delivers the due entries in queue order, each in its own
     * failure regime (one listener's exception never stops its
     * peers -- KDD-EA2), requeues the retries with their backoff
     * ready-at, dead-letters the exhausted, and quarantines the
     * listener whose attributed dead letters reach the threshold.
     * Time is this parameter (KDD-EA4): the regime owns no clock.
     */
    public function drain(int $now): DrainOutcome
    {
        return $this->asyncDelivery->drain($now);
    }
}
