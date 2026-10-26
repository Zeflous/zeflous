<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use Override;
use Zef\Framework\Event\AsyncDelivery;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventListener;
use Zef\Framework\Event\ListenerPriority;
use Zef\Framework\Event\ListenerSubscription;

/**
 * A listener that defers itself once during its own delivery --
 * the drain-fold's stand-in for the self-feeding listener, pinned
 * by KDD-EA9: the entry it defers is next-pass material, so the
 * pass that delivered the listener never also delivers what it
 * spawned, and no self-feeding loop can keep a drain alive.
 *
 * @internal
 */
final class SelfDeferringListener implements EventListener
{
    private bool $deferred = false;

    public function __construct(private readonly AsyncDelivery $asyncDelivery)
    {
    }

    #[Override]
    public function handle(Event $event): void
    {
        if ($this->deferred) {
            return;
        }

        $this->deferred = true;
        $this->asyncDelivery->defer(
            $event,
            [new ListenerSubscription($this, new ListenerPriority(0))],
        );
    }
}
