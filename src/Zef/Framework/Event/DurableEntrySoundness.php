<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The durable drain's soundness fold: whether a due entry is
 * sound to deliver at all -- the listener identity resolved to a
 * listener object THIS deployment answers, and the record's event
 * rebuilt and re-validated against the definition that is LIVE at
 * drain time. An entry that fails either pre-flight is already
 * dead: the fold records its story in the store through the
 * settlement ordering (the story first, the settle second) and
 * answers null, so the drain pass counts a dead letter and moves
 * on -- the unresolved-listener cause when no listener answers
 * the identity, the stale-shape cause when the event is no longer
 * defined or the payload no longer answers the shape the
 * definition now demands, each with the refusal's own message
 * spelled: the operator reads exactly how the deployment moved.
 *
 * The fold owns the pre-flight judgment only: which failure a
 * SOUND delivery becomes is the attempt and judgment folds' own
 * question, and the in-process books stay their own state.
 */
final readonly class DurableEntrySoundness
{
    /** The dead-letter cause of an identity no listener resolves to. */
    public const string UNRESOLVED_LISTENER = 'unresolved-listener';

    /** The dead-letter cause of a record the live definitions no longer accept. */
    public const string STALE_SHAPE = 'stale-shape';

    public function __construct(
        private EventListenerResolver $eventListenerResolver,
        private EventBook $eventBook,
        private DurableSettlement $durableSettlement,
    ) {
    }

    /**
     * Answers the sound delivery one due entry stands for, or null
     * when the entry was already dead -- resolved, rebuilt and
     * re-validated, or recorded and settled as one of the two
     * pre-flight causes.
     */
    public function sound(DeliveryEnvelope $deliveryEnvelope, int $now): ?DeferredDelivery
    {
        $eventListener = $this->eventListenerResolver->resolve($deliveryEnvelope->listenerIdentity());

        if (!$eventListener instanceof \Zef\Framework\Event\EventListener) {
            $this->durableSettlement->dead(
                $deliveryEnvelope,
                self::UNRESOLVED_LISTENER,
                'No listener answers the listener identity'
                . " '" . $deliveryEnvelope->listenerIdentity()->spelling() . "'.",
                $now,
            );

            return null;
        }

        try {
            $event = new Event(
                $deliveryEnvelope->eventName(),
                $this->eventBook->shapeFor($deliveryEnvelope->eventName())->validate($deliveryEnvelope->values()),
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->durableSettlement->dead($deliveryEnvelope, self::STALE_SHAPE, $invalidArgumentException->getMessage(), $now);

            return null;
        }

        return new DeferredDelivery(
            $event,
            $eventListener,
            $deliveryEnvelope->attempt(),
            $deliveryEnvelope->readyAt(),
        );
    }
}
