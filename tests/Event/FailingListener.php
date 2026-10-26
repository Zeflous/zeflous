<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use Override;
use RuntimeException;
use Zef\Framework\Event\Event;
use Zef\Framework\Event\EventListener;

/**
 * A listener that fails instead of working: every delivery throws
 * the exception it was constructed with, and the throw count is
 * kept so a test can assert exactly how many attempts reached it.
 * The deferred regime's stand-in for a broken listener -- the
 * poison pill itself, when a test wants one.
 *
 * @internal
 */
final class FailingListener implements EventListener
{
    private int $thrown = 0;

    public function __construct(private readonly string $message = 'the listener failed')
    {
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
