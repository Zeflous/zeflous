<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The event itself: a name and its validated payload, the one
 * object every listener of a dispatch receives. Immutable by
 * construction -- every fold that builds it (the dispatcher's own
 * dispatch) composes two already-frozen value objects, so a
 * listener cannot rewrite what its peers will read.
 */
final readonly class Event
{
    public function __construct(private EventName $eventName, private EventPayload $eventPayload)
    {
    }

    /**
     * The event's name -- the spelling the dispatch was addressed
     * by, so a shared listener can tell its events apart.
     */
    public function name(): EventName
    {
        return $this->eventName;
    }

    /**
     * The event's validated payload -- every field the shape
     * demands, present and kind-checked before any listener ran.
     */
    public function payload(): EventPayload
    {
        return $this->eventPayload;
    }
}
