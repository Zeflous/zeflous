<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;
use Throwable;

/**
 * The event dispatcher's public face: the synchronous vocabulary a
 * host application spells -- define an event, subscribe a listener,
 * dispatch a payload, the wildcard pair the roadmap's advanced
 * listener management demands, and the unsubscribes the
 * runtime-modification doctrine demands. The deferred pair the
 * async regime runs on is its own composed vocabulary: ask this
 * dispatcher for it with {@see deferredDelivery()} and the engine
 * the host spells.
 *
 * The whole registry is fail-closed on the event's name: the
 * {@see EventBook} refuses a subscription, an unsubscribe and a
 * dispatch for an undefined name, because a typo in any of the
 * three is exactly the bug the definition surface exists to catch.
 * A wildcard subscription is its own contract -- the pattern is
 * the address, and the events it matches are the events the
 * application defines, each carrying each definition's own
 * validated shape. Payload validation is the delivery boundary's
 * own job: the shape's check runs before the first listener is
 * invoked, so a mis-shaped payload never reaches a listener's
 * body, exact or wildcard alike -- and the SAME boundary owns the
 * deferred defer (KDD-EA10): the producer's mistake surfaces at
 * the producer's call, never at a drain.
 *
 * Synchronous execution is unchanged by declaration: listeners run
 * in the dispatch caller's own failure regime -- a listener's
 * exception bubbles to the caller, and the later listeners of that
 * dispatch do not run. The freeze and the pool-walk every delivery
 * reads live in the {@see DeliveryPlan} fold -- the EventBook
 * precedent's shape, the dispatcher staying the vocabulary.
 */
final readonly class EventDispatcher
{
    private EventBook $eventBook;

    private WildcardSubscriptions $wildcardSubscriptions;

    private DeliveryPlan $deliveryPlan;

    public function __construct()
    {
        $this->eventBook = new EventBook();
        $this->wildcardSubscriptions = new WildcardSubscriptions();
        $this->deliveryPlan = new DeliveryPlan($this->eventBook, $this->wildcardSubscriptions);
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
     * Dispatches an event: validates the payload against the event's
     * shape, freezes it into the {@see Event} every listener
     * receives, and hands the event to the delivery pool's listeners
     * in order -- the exact-name subscribers and the matching
     * wildcard subscribers sorted as one pool, priority first; among
     * equals, the exact-name listener stands before the wildcard (the
     * targeted answer before the general observation, the one tie the
     * pool itself must break), and each kind keeps its own
     * registration/declaration order.
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
        $preparedDelivery = $this->deliveryPlan->prepare($eventName, $values);
        $listenerSubscriptions = $preparedDelivery->pool();

        foreach ($listenerSubscriptions as $listenerSubscription) {
            $listenerSubscription->listener()->handle($preparedDelivery->event());
        }

        return new DispatchOutcome($eventName, \count($listenerSubscriptions));
    }

    /**
     * The deferred vocabulary over this dispatcher's own plan: the
     * defer/drain pair composed one-way with the engine the host
     * spells. A dispatcher that was never asked for one simply has
     * no deferred verbs to call -- the sync-only shape, answered
     * structurally instead of by a refusal.
     */
    public function deferredDelivery(AsyncDelivery $asyncDelivery): DeferredEventDispatcher
    {
        return new DeferredEventDispatcher($this->deliveryPlan, $asyncDelivery);
    }

    /**
     * The durable vocabulary over this dispatcher's own plan: the
     * defer/drain pair composed one-way with the persistence, the
     * resolution and the policy the host spells -- the arm whose
     * entries survive the process that admitted them. The event
     * book the plan already reads is the same book the drain
     * re-validates each loaded record against, so the live
     * definitions stay the one authority on what a deferred entry
     * means, in-process and across the boundary alike.
     */
    public function durableDelivery(
        DeferredDeliveryStore $deferredDeliveryStore,
        EventListenerResolver $eventListenerResolver,
        RetryPolicy $retryPolicy,
        int $quarantineThreshold,
    ): DurableEventDispatcher {
        return new DurableEventDispatcher(
            $this->deliveryPlan,
            new DurableDelivery(
                $deferredDeliveryStore,
                $eventListenerResolver,
                $this->eventBook,
                $retryPolicy,
                $quarantineThreshold,
            ),
        );
    }
}
