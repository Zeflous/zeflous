<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Binds a route entry's declared host to its parsed path: the host is
 * canonicalised through the grammar, then refused when it repeats a
 * parameter name the path already declares -- one name cannot be both a
 * path capture and a host capture.
 *
 * @internal
 */
final class RouteHostBinding
{
    /**
     * The canonicalised declared host, or null for a host-agnostic route.
     *
     * @return null|non-empty-string
     */
    public static function declared(?string $host, RoutePath $routePath): ?string
    {
        if ($host === null) {
            return null;
        }

        $canonical = RouteHostGrammar::canonical($host);
        $collisions = array_intersect($routePath->parameterNames(), RouteHostParameters::parameterNames($canonical));

        if ($collisions !== []) {
            throw new ConfigException(
                \sprintf('The route host and path must not repeat parameter name(s): %s.', implode(', ', $collisions)),
            );
        }

        return $canonical;
    }
}
