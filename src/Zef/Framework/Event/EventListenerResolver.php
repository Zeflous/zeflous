<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The durable arm's resolution port: the answer side of the
 * identity vocabulary. A durable record carries a
 * {@see ListenerIdentity}; the drain hands that spelling to this
 * port and expects the listener object that must handle the
 * event -- the container-backed table a host composes, the
 * roadmap's own handler-resolution doctrine spelled as a port the
 * Event layer reaches one-way.
 *
 * The null answer is an ANSWER, not a failure: a long-lived
 * durable store will outlive services that were retired, and "no
 * listener answers this identity" is the fact a drain dead-letters
 * on, with the identity named in the story -- the honest spelling
 * of a deployment whose listeners moved, never an exception the
 * whole drain halts on.
 */
interface EventListenerResolver
{
    /**
     * Resolves one listener identity into the listener that must
     * answer it, or null when no listener in this deployment does
     * -- the answer the durable drain's dead-letter arm owns.
     */
    public function resolve(ListenerIdentity $listenerIdentity): ?EventListener;
}
