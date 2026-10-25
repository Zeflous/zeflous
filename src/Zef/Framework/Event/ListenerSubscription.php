<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * One listener's registration: the listener object and the
 * priority it stands at, frozen together the moment it joins the
 * registry. The pair is what the dispatch order sorts and what an
 * unsubscribe removes -- identity of the listener object alone,
 * never the pair's history, because re-subscribing after a removal
 * is a new standing with a new pair.
 */
final readonly class ListenerSubscription
{
    public function __construct(private EventListener $eventListener, private ListenerPriority $listenerPriority)
    {
    }

    /**
     * The subscribed listener -- the object whose handle() one
     * dispatch calls.
     */
    public function listener(): EventListener
    {
        return $this->eventListener;
    }

    /**
     * The standing the subscription was registered at -- the
     * integer the registry's order compares.
     */
    public function priority(): ListenerPriority
    {
        return $this->listenerPriority;
    }
}
