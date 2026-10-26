<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use Override;
use Zef\Framework\Event\EventListener;
use Zef\Framework\Event\EventListenerResolver;
use Zef\Framework\Event\ListenerIdentity;

/**
 * The resolver stand-in: a fixed table of identities to listeners,
 * answering null for every identity the table does not carry --
 * the null answer the durable drain's dead-letter arm owns, spelled
 * by the fixture a test arms.
 *
 * @internal
 */
final readonly class FixedResolver implements EventListenerResolver
{
    /** @param array<string, EventListener> $table the identities this deployment resolves */
    public function __construct(private array $table = [])
    {
    }

    #[Override]
    public function resolve(ListenerIdentity $listenerIdentity): ?EventListener
    {
        return $this->table[$listenerIdentity->spelling()] ?? null;
    }
}
