<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;
use Throwable;

/**
 * The event dispatcher's public face: the vocabulary a host
 * application spells -- define an event, subscribe a listener,
 * dispatch a payload, the wildcard pair the roadmap's advanced
 * listener management demands, the unsubscribes the
 * runtime-modification doctrine demands, and the deferred pair
 * ({@see defer()} / {@see drain()}) the async regime runs on.
 *
 * The whole registry is fail-closed on the event's name: the
 * {@see EventBook} refuses a subscription, an unsubscribe and a
 * dispatch for an undefined name, because a typo in any of the
 * three is exactly the bug the definition surface exists to catch.
 * A wildcard subscription is its own contract -- the pattern is
 * the address, and the events it matches are the events the
 * application defines, each carrying each definition's own
 * validated shape. Payload validation is the dispatch boundary's
 * own job: the shape's check runs before the first listener is
 * invoked, so a mis-shaped payload never reaches a listener's
 * body, exact or wildcard alike -- and the SAME boundary owns
 * defer (KDD-EA10): the producer's mistake surfaces at the
 * producer's call, never at a drain.
 *
 * Synchronous execution is unchanged by declaration: listeners
 * run in the dispatch caller's own failure regime -- a listener's
 * exception bubbles to the caller, and the later listeners of
 * that dispatch do not run. The deferred regime is a separate
 * pair of verbs over an injected {@see AsyncDelivery} engine,
 * never a policy hidden inside the synchronous fold: a
 * dispatcher constructed without one is the legal sync-only
 * shape, and its deferred verbs fail closed with the
 * construction spelled.
 */
final readonly class EventDispatcher
{
    private EventBook $eventBook;

    private WildcardSubscriptions $wildcardSubscriptions;

    public function __construct(private ?AsyncDelivery $asyncDelivery = null)
    {
        $this->eventBook = new EventBook();
        $this->wildcardSubscriptions = new WildcardSubscriptions();
    }

    /**
     * Defines an event's name and payload contract, opening its
     * (empty) listener registry.
     *
     * @throws InvalidArgumentException when the name is already
     *                                  defined
     */
    public function define(EventName $eventName, EventShape $eventShape): void
    {
        $this->eventBook->define($eventName, $eventShape);
    }

    /**
     * Subscribes a listener to a defined event at a priority.
     *
     * @throws InvalidArgumentException when the event is not
     *                                  defined, or the listener object is already subscribed
     *                                  to it
     */
    public function subscribe(
        EventName $eventName,
        EventListener $eventListener,
        ListenerPriority $listenerPriority,
    ): void {
        $this->eventBook->registryFor($eventName)->add(
            new ListenerSubscription($eventListener, $listenerPriority),
        );
    }

    /**
     * Subscribes a listener under a wildcard pattern at a priority.
     *
     * A wildcard subscription needs no definition: the pattern is
     * its own contract, and the events it matches are the events the
     * application defines -- each dispatch validating its payload
     * against the matched event's own shape before any listener,
     * wildcard included, is invoked.
     *
     * @throws InvalidArgumentException when the listener object is
     *                                  already subscribed under this pattern
     */
    public function subscribeWildcard(
        EventPattern $eventPattern,
        EventListener $eventListener,
        ListenerPriority $listenerPriority,
    ): void {
        $this->wildcardSubscriptions->add(
            $eventPattern,
            new ListenerSubscription($eventListener, $listenerPriority),
        );
    }

    /**
     * Removes a listener's wildcard standing, answering whether one
     * was removed -- the runtime-modification verb for pattern
     * teardown and test isolation.
     */
    public function unsubscribeWildcard(
        EventPattern $eventPattern,
        EventListener $eventListener,
    ): bool {
        return $this->wildcardSubscriptions->remove($eventPattern, $eventListener);
    }

    /**
     * Removes a listener's subscription, answering whether one was
     * removed -- the runtime-modification verb for plugin teardown
     * and test isolation.
     */
    public function unsubscribe(EventName $eventName, EventListener $eventListener): bool
    {
        return $this->eventBook->registryFor($eventName)->remove($eventListener);
    }

    /**
     * Dispatches an event: validates the payload against the
     * event's shape, freezes it into the {@see Event} every
     * listener receives, and hands the event to the dispatch pool's
     * listeners in order -- the exact-name subscribers and the
     * matching wildcard subscribers sorted as one pool, priority
     * first; among equals, the exact-name listener stands before the
     * wildcard (the targeted answer before the general observation,
     * the one tie the pool itself must break), and each kind keeps
     * its own registration/declaration order.
     *
     * @param array<array-key, mixed> $values the producer's payload
     *
     * @throws InvalidArgumentException when the event is not
     *                                  defined or the payload does not answer its shape
     * @throws Throwable                whatever a listener threw -- synchronous
     *                                  dispatch declares the caller the failure regime
     */
    public function dispatch(EventName $eventName, array $values): DispatchOutcome
    {
        $event = $this->frozenEvent($eventName, $values);
        $listenerSubscriptions = $this->deliveryPool($eventName);

        foreach ($listenerSubscriptions as $listenerSubscription) {
            $listenerSubscription->listener()->handle($event);
        }

        return new DispatchOutcome($eventName, \count($listenerSubscriptions));
    }

    /**
     * Defers an event: validates the payload at this same boundary
     * ({@see dispatch()} refuses a mis-shaped payload before any
     * listener runs, and so does defer -- KDD-EA10), freezes the
     * event, snapshots the delivery pool, and admits one pending
     * delivery per pool listener the quarantine has not given up
     * on. The snapshot is the message (KDD-EA3): a listener
     * subscribed after this call does not receive the event, and an
     * unsubscribe after it does not retract it.
     *
     * @param array<array-key, mixed> $values the producer's payload
     *
     * @throws InvalidArgumentException when the event is not
     *                                  defined, the payload does not answer its shape, this
     *                                  dispatcher was constructed without a deferred-delivery
     *                                  engine, or the queue's capacity would be exceeded (the
     *                                  backpressure refusal -- drain before deferring more)
     */
    public function defer(EventName $eventName, array $values): DeferralOutcome
    {
        if (!$this->asyncDelivery instanceof AsyncDelivery) {
            throw new InvalidArgumentException(
                'defer needs a deferred-delivery engine; construct the'
                . ' dispatcher with one (new EventDispatcher(new'
                . ' AsyncDelivery(...))) -- a dispatcher built without one'
                . ' is the legal sync-only shape.',
            );
        }

        return $this->asyncDelivery->defer(
            $this->frozenEvent($eventName, $values),
            $this->deliveryPool($eventName),
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
     *
     * @throws InvalidArgumentException when this dispatcher was
     *                                  constructed without a deferred-delivery engine
     */
    public function drain(int $now): DrainOutcome
    {
        if (!$this->asyncDelivery instanceof AsyncDelivery) {
            throw new InvalidArgumentException(
                'drain needs a deferred-delivery engine; construct the'
                . ' dispatcher with one (new EventDispatcher(new'
                . ' AsyncDelivery(...))) -- a dispatcher built without one'
                . ' is the legal sync-only shape.',
            );
        }

        return $this->asyncDelivery->drain($now);
    }

    /**
     * Freezes the event: the payload validated against the name's
     * own shape, the one object every listener of the delivery
     * receives. The boundary both verbs share -- the producer's
     * mistake surfaces at the producer's call.
     *
     * @param array<array-key, mixed> $values the producer's payload
     *
     * @throws InvalidArgumentException when the event is not
     *                                  defined or the payload does not answer its shape
     */
    private function frozenEvent(EventName $eventName, array $values): Event
    {
        return new Event(
            $eventName,
            $this->eventBook->shapeFor($eventName)->validate($values),
        );
    }

    /**
     * The delivery pool: the exact-name subscribers and the
     * matching wildcard subscribers sorted as one pool, priority
     * first; among equals, the exact-name listener stands before
     * the wildcard, and each kind keeps its own order. The fold
     * both delivery verbs share -- the synchronous dispatch and
     * the deferred admission read the SAME pool, so the deferred
     * arm inherits the ordering doctrine byte-for-byte.
     *
     * @return list<ListenerSubscription>
     */
    private function deliveryPool(EventName $eventName): array
    {
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

        return $listenerSubscriptions;
    }
}
