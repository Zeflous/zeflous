<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;
use Throwable;

/**
 * The event dispatcher's public face: the three-verb vocabulary a
 * host application spells -- define an event, subscribe a
 * listener, dispatch a payload -- plus the unsubscribe the
 * roadmap's runtime-modification doctrine demands.
 *
 * The whole registry is fail-closed on the event's name: the
 * {@see EventBook} refuses a subscription, an unsubscribe and a
 * dispatch for an undefined name, because a typo in any of the
 * three is exactly the bug the definition surface exists to catch.
 * Payload validation is the dispatch boundary's own job: the
 * shape's check runs before the first listener is invoked, so a
 * mis-shaped payload never reaches a listener's body.
 *
 * Execution is synchronous by declaration: listeners run in the
 * dispatch caller's own failure regime -- a listener's exception
 * bubbles to the caller, and the later listeners of that dispatch
 * do not run. The retry/dead-letter/async regime the roadmap's
 * enterprise phases demand is deliberately a named follow-up, not
 * a silent policy hidden inside this fold.
 */
final readonly class EventDispatcher
{
    private EventBook $eventBook;

    public function __construct()
    {
        $this->eventBook = new EventBook();
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
     * listener receives, and hands the event to each subscribed
     * listener in the registry's order -- priority first,
     * registration order among equals.
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
        $event = new Event(
            $eventName,
            $this->eventBook->shapeFor($eventName)->validate($values),
        );

        $listenerSubscriptions = $this->eventBook->registryFor($eventName)->ordered();

        foreach ($listenerSubscriptions as $listenerSubscription) {
            $listenerSubscription->listener()->handle($event);
        }

        return new DispatchOutcome($eventName, \count($listenerSubscriptions));
    }
}
