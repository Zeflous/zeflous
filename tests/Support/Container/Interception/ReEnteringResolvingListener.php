<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Override;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ResolvingListener;

/**
 * The resolving-listener re-entrancy probe (issue #791's regression
 * double): a listener that resolves, through the container it captured
 * as its constructor argument, the very identifier whose construction
 * it observes starting -- the spelling the cycle-guard's lifted frame
 * must answer with the catchable circular dependency refusal, because
 * the frame is already open when the listener fires (the resolving
 * event rides before the onion), where the guard-less spelling
 * recursed without bound.
 */
final readonly class ReEnteringResolvingListener implements ResolvingListener
{
    public function __construct(
        private Container $container,
    ) {
    }

    #[Override]
    public function resolving(string $id): void
    {
        if ($id !== 'reentry.target') {
            return;
        }

        $this->container->get($id);
    }
}
