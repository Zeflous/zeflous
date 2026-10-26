<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The durable vocabulary: the defer/drain pair over a delivery
 * plan and the durable regime's engine, composed one-way with the
 * persistence and the resolution the host spells -- the mirror of
 * {@see DeferredEventDispatcher}, for the deliveries that must
 * survive the process that admitted them. A host asks its
 * dispatcher for this facade with
 * {@see EventDispatcher::durableDelivery()}; a dispatcher that was
 * never asked simply has no durable verbs to call, exactly as the
 * in-process arm spells its own one-way composition.
 *
 * The defer validates at the boundary (KDD-EA10, carried over),
 * freezes the event, snapshots the delivery pool, and saves one
 * serialized entry per surviving pool listener into the store; the
 * drain loads the store's due entries, re-validates each against
 * the live definitions, and delivers in the same per-delivery
 * failure regime the in-process arm runs -- with the store's
 * at-least-once protocol underneath: a crash costs a redelivery,
 * never a loss.
 */
final readonly class DurableEventDispatcher
{
    public function __construct(
        private DeliveryPlan $deliveryPlan,
        private DurableDelivery $durableDelivery,
    ) {
    }

    /**
     * Defers an event durably: validates the payload at the
     * delivery boundary (the producer's mistake surfaces at the
     * producer's call -- KDD-EA10), freezes the event, snapshots
     * the delivery pool, and saves one serialized entry per pool
     * listener the quarantine has not given up on -- demanding
     * that every pool listener declare the identity its entry is
     * spelled by. The snapshot is the message (KDD-EA3, carried
     * over): a listener subscribed after this call does not join,
     * and an unsubscribe after it does not retract.
     *
     * @param array<array-key, mixed> $values the producer's payload
     *
     * @throws InvalidArgumentException when the event is not
     *                                  defined, the payload does not answer its shape, a pool
     *                                  listener declares no durable identity, or the store
     *                                  refuses the batch (the backpressure refusal -- drain
     *                                  before deferring more)
     */
    public function defer(EventName $eventName, array $values): DeferralOutcome
    {
        return $this->durableDelivery->defer(
            $this->deliveryPlan->prepare($eventName, $values),
        );
    }

    /**
     * Runs one drain pass of the durable regime at the given now:
     * takes the store's due entries, resolves each listener
     * identity, re-validates each event against the live
     * definitions, and delivers in the same per-delivery failure
     * regime the in-process arm runs -- requeueing the retries
     * with their backoff ready-at, recording the spent entries'
     * stories in the store's dead-letter records, and settling
     * every answered entry. Time is this parameter (KDD-EA4,
     * carried over): the regime owns no clock.
     */
    public function drain(int $now): DrainOutcome
    {
        return $this->durableDelivery->drain($now);
    }
}
