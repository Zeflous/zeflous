<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Closure;
use Override;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ServiceInterceptor;

/**
 * The re-entrancy probe (issue #791's regression double): an interceptor
 * that resolves, through the container it captured as its constructor
 * argument, the very identifier it intercepts -- the spelling the
 * cycle-guard's lifted frame must answer with the catchable circular
 * dependency refusal, never the unbounded recursion the guard-less
 * spelling recursed into (a PHP stack exhaustion is a fatal no caller
 * can catch, the opposite of the container's own contract).
 *
 * The container arrives as an ordinary service value (a registered
 * factory answering the container itself), because the interceptor's
 * own construction must not ride the seam it arms; the re-entry is
 * armed for the one target identifier alone, so a sibling construction
 * still answers honestly and the guard's residue-freedom can be read
 * from the same registry.
 */
final readonly class ReEnteringInterceptor implements ServiceInterceptor
{
    public function __construct(
        private Container $container,
    ) {
    }

    #[Override]
    public function intercept(string $id, Closure $next): mixed
    {
        if ($id !== 'reentry.target') {
            return $next();
        }

        return $this->container->get($id);
    }
}
