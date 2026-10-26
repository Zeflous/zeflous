<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use Override;
use Zef\Framework\Event\DeferredDeliveryStore;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DurableEventListener;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\ListenerIdentity;

/**
 * A durable listener that feeds the store instead of working: every
 * delivery saves one fresh entry addressed to another identity --
 * the drain-snapshot doctrine's own stand-in, because the entry it
 * saves during a drain must stay next-pass material, never join the
 * pass that is already running.
 *
 * @internal
 */
final readonly class StoreWritingListener implements DurableEventListener
{
    public function __construct(
        private string $identity,
        private DeferredDeliveryStore $deferredDeliveryStore,
        private string $fedIdentity,
    ) {
    }

    #[Override]
    public function listenerIdentity(): ListenerIdentity
    {
        return new ListenerIdentity($this->identity);
    }

    #[Override]
    public function handle(Event $event): void
    {
        $this->deferredDeliveryStore->save(new DeliveryEnvelope(
            DeliveryEntryId::mint(),
            new EventName('order.paid'),
            ['amount' => $event->payload()->value('amount')],
            new ListenerIdentity($this->fedIdentity),
            1,
            0,
        ));
    }
}
