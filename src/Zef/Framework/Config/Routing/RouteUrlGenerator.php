<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Reverse routing: a named route plus parameter values become a URL the
 * engine's own tree resolves back to that route (see `ROADMAP.md`,
 * "Configuration System + DSL + Radix Tree" -- the round-trip guarantee).
 *
 * Generation is fail-closed exactly like compilation: an unknown name, a
 * missing parameter, an excess parameter or a value the route rejects
 * ({@see RouteUrlSegments} and the classes under it) all raise a
 * {@see ConfigException} naming the route and the parameter -- a
 * generated URL can never fail to match its own route.
 *
 * @api
 */
final readonly class RouteUrlGenerator
{
    public function __construct(private RadixTree $radixTree)
    {
    }

    /**
     * Generates the URL of a named route from its parameter values.
     *
     * The values are validated fail-closed (scalar or `Stringable`, non-
     * empty, constraint-satisfying), so the map's shape is judged here,
     * exactly like {@see CompiledRoute::fromArray()} judges its entry.
     *
     * @param array<string, mixed> $params one value per route parameter
     */
    public function generate(string $name, array $params): string
    {
        $route = $this->radixTree->route($name);

        $rendered = RouteUrlSegments::render($route, $params);

        return '/' . implode('/', $rendered);
    }
}
