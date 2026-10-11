<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Closure;

/**
 * A resolved route handler, normalised into one invocation contract: the
 * dispatcher calls it with one argument -- the captured parameter map,
 * keyed by name -- and whatever it returns travels on as the dispatch
 * result.
 *
 * The wrapper exists so the resolver's output is a typed value the static
 * analysers can carry signature-first (a bare `Closure` return has no
 * callable signature for them to check, and the container's own `mixed`
 * resolution cannot be narrowed into one). Constructed only by
 * {@see RouteHandlerResolver}, never by callers.
 *
 * @internal consumed through {@see RouteDispatcher}
 */
final readonly class RouteHandler
{
    /**
     * @param Closure(array<string, string>): mixed $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    /**
     * @param array<string, string> $parameters
     */
    public function __invoke(array $parameters): mixed
    {
        return ($this->handler)($parameters);
    }
}
