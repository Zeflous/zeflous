<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * What one dispatch answered with: the event's name and the count
 * of listeners the event was delivered to. The count is the
 * synchronous dispatch's whole observable outcome -- no return
 * values are collected (the listener contract returns void), so
 * the number is what a test pins and what an observability fold
 * will one day timestamp.
 */
final readonly class DispatchOutcome
{
    public function __construct(private EventName $eventName, private int $deliveredTo)
    {
    }

    /**
     * The name the dispatch was addressed by.
     */
    public function name(): EventName
    {
        return $this->eventName;
    }

    /**
     * How many listeners the event was delivered to -- the
     * subscription count at dispatch time, zero included.
     */
    public function deliveredTo(): int
    {
        return $this->deliveredTo;
    }
}
