<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The state a {@see RouteDsl} group must return to when it closes: the
 * prefix and the scope middleware in force when the group opened.
 *
 * The snapshot is taken before the group's prefix is composed, so
 * restoring it -- whether the group's closure returned or threw --
 * leaves the enclosing scope exactly as it was, and a group can never
 * leak its prefix or middleware outward.
 *
 * @internal
 */
final readonly class RouteDslSnapshot
{
    /**
     * @param list<string> $scopeMiddleware
     */
    public function __construct(
        public RoutePrefix $routePrefix,
        public array $scopeMiddleware,
    ) {
    }
}
