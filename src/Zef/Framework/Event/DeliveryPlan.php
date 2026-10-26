<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The delivery plan fold: what one delivery needs -- the frozen
 * event and the ordered pool -- answered as one {@see PreparedDelivery}
 * value. This is the fold the dispatcher's own difficulty ceiling
 * forced out (the EventBook precedent's shape): the freeze and the
 * pool-walk are one concern -- reading the books the way a delivery
 * reads them -- and the dispatcher stays the vocabulary.
 *
 * The pool is the exact-name subscribers and the matching wildcard
 * subscribers sorted as one pool, priority first; among equals, the
 * exact-name listener stands before the wildcard (the targeted answer
 * before the general observation, the one tie the pool itself must
 * break), and each kind keeps its own registration/declaration order.
 */
final readonly class DeliveryPlan
{
    public function __construct(
        private EventBook $eventBook,
        private WildcardSubscriptions $wildcardSubscriptions,
    ) {
    }

    /**
     * Prepares one delivery: validates the payload against the
     * name's own shape (the producer's mistake surfaces at the
     * producer's call -- KDD-EA10, the boundary every delivery verb
     * shares), freezes the event, and walks the ordered pool.
     *
     * @param array<array-key, mixed> $values the producer's payload
     *
     * @throws InvalidArgumentException when the event is not defined
     *                                  or the payload does not answer its shape
     */
    public function prepare(EventName $eventName, array $values): PreparedDelivery
    {
        $event = new Event($eventName, $this->eventBook->shapeFor($eventName)->validate($values));

        $listenerSubscriptions = [
            ...$this->eventBook->registryFor($eventName)->ordered(),
            ...$this->wildcardSubscriptions->matching($eventName),
        ];

        usort(
            $listenerSubscriptions,
            static fn (
                ListenerSubscription $left,
                ListenerSubscription $right,
            ): int => $right->priority()->standing() <=> $left->priority()->standing(),
        );

        return new PreparedDelivery($event, $listenerSubscriptions);
    }
}
