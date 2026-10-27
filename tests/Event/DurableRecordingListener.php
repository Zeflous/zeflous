<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use Override;
use Zef\Framework\Event\DurableEventListener;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\ListenerIdentity;

/**
 * A durable listener that records instead of working: its tag
 * joins the shared log in its running order, the events it
 * received are kept for identity assertions, and its identity is
 * the spelling it was constructed with -- the test suite's
 * stand-in for an application service that declared its durable
 * name.
 *
 * @internal
 */
final class DurableRecordingListener implements DurableEventListener
{
    /** @var list<Event> */
    private array $received = [];

    public function __construct(
        private readonly EventLog $eventLog,
        private readonly string $tag,
        private readonly string $identity,
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
        $this->eventLog->record($this->tag);
        $this->received[] = $event;
    }

    /**
     * The events this listener was handed, in delivery order.
     *
     * @return list<Event>
     */
    public function received(): array
    {
        return $this->received;
    }
}
