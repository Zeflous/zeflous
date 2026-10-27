<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The listener contract the durable arm admits: an
 * {@see EventListener} that declares the identity its deliveries
 * are spelled by. The identity is the listener's own declaration,
 * never a guess the framework makes -- a service knows its stable
 * name, and only that name lets a later process resolve the
 * delivery back into the object that must answer it.
 *
 * A listener that implements only {@see EventListener} stays
 * legal everywhere else: the synchronous dispatch and the
 * in-process defer keep admitting it. Only the durable defer
 * refuses it, by class name, because promising a delivery no
 * later process could resolve is the one dishonesty this
 * vocabulary exists to prevent.
 */
interface DurableEventListener extends EventListener
{
    /**
     * The identity this listener's deferred deliveries are spelled
     * by -- the spelling a durable record carries and a resolver
     * answers, stable across processes and deployments.
     */
    public function listenerIdentity(): ListenerIdentity;
}
