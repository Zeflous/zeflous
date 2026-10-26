<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use Override;
use RuntimeException;
use Zef\Framework\Event\DurableEventListener;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\ListenerIdentity;

/**
 * A durable listener that fails instead of working: every delivery
 * throws the exception it was constructed with, the throw count is
 * kept so a test can assert exactly how many attempts reached it,
 * and its identity is the spelling it was constructed with -- the
 * durable regime's stand-in for a broken service, the poison pill
 * itself, when a test wants one.
 *
 * @internal
 */
final class DurableFailingListener implements DurableEventListener
{
    private int $thrown = 0;

    public function __construct(
        private readonly string $identity,
        private readonly string $message = 'the durable listener failed',
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
        ++$this->thrown;

        throw new RuntimeException($this->message);
    }

    /**
     * How many deliveries reached this listener -- the attempt
     * count the retry policy actually spent on it.
     */
    public function thrown(): int
    {
        return $this->thrown;
    }
}
