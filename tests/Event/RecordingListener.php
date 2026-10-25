<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use Override;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventListener;

/**
 * A listener that records instead of working: its tag joins the
 * shared log in its running order, and the events it received are
 * kept for identity assertions. The test suite's stand-in for a
 * real listener -- everything the dispatcher contract promises
 * about delivery is observable through this one object.
 *
 * @internal
 */
final class RecordingListener implements EventListener
{
    /** @var list<Event> */
    private array $received = [];

    public function __construct(
        private readonly EventLog $eventLog,
        private readonly string $tag,
    ) {
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
