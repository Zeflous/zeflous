<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Event;

use Override;
use Zef\Framework\Adapters\Event\WorkerDrainSentinel;
use Zef\Framework\Event\DurableEventListener;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\ListenerIdentity;
use Zef\Test\Event\EventLog;

/**
 * A durable listener that arms the shutdown sentinel WHILE its
 * delivery is in flight, then records its tag: the mid-pass stop,
 * spelled by the listener the pass is delivering. Its whole
 * purpose is the observation the between-passes doctrine demands
 * (KDD-DW2) -- the pass that armed the sentinel must still
 * complete, and its pool peers must still deliver, because the
 * sentinel never interrupts a pass in flight.
 *
 * @internal
 */
final readonly class SentinelArmingListener implements DurableEventListener
{
    public function __construct(
        private EventLog $eventLog,
        private WorkerDrainSentinel $workerDrainSentinel,
        private string $tag,
        private string $identity,
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
        $this->workerDrainSentinel->arm();
        $this->eventLog->record($this->tag);
    }
}
