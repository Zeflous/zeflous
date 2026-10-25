<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The listener contract: one method, the event in, nothing out.
 * A listener does its work for its own reasons and returns void
 * on purpose -- the dispatch outcome counts deliveries, never
 * collects return values, because two listeners answering the
 * same event with two different answers is not a value the
 * dispatcher could pick between.
 *
 * Register any callable-shaped object that implements this face;
 * the registry orders it against its peers by the priority the
 * subscription carries.
 */
interface EventListener
{
    /**
     * Handles the event this listener is subscribed to. Exceptions
     * bubble to the dispatch caller: the synchronous dispatcher
     * runs listeners in the caller's own failure regime, and the
     * retry/dead-letter regime the roadmap demands is a named
     * follow-up of the async slice, not a silent swallow here.
     */
    public function handle(Event $event): void;
}
