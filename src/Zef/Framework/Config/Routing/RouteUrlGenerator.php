<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

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
        $hostParameters = RouteHost::parameterNames($route->host() ?? '');
        $pathParameters = array_diff_key($params, array_fill_keys($hostParameters, true));
        $rendered = RouteUrlSegments::render($route, $pathParameters);

        return '/' . implode('/', $rendered);
    }

    /**
     * Generates an absolute URL for a route with a declared host pattern.
     *
     * Host parameters must be valid DNS labels; the scheme is restricted to
     * HTTP(S), and path parameters retain the ordinary reverse-routing checks.
     *
     * @param array<string, mixed> $params route host and path parameters
     */
    public function generateAbsolute(string $name, array $params, string $scheme = 'https'): string
    {
        if (!\in_array(mb_strtolower($scheme), ['http', 'https'], true)) {
            throw new ConfigException('The absolute route URL scheme must be "http" or "https".');
        }

        $route = $this->radixTree->route($name);
        $hostPattern = $route->host();

        if ($hostPattern === null) {
            throw new ConfigException(\sprintf('The route "%s" has no host pattern for absolute URL generation.', $name));
        }

        $hostParameters = RouteHost::parameterNames($hostPattern);
        $hostValues = array_intersect_key($params, array_fill_keys($hostParameters, true));
        $host = RouteHost::render($hostPattern, $hostValues);
        $pathValues = array_diff_key($params, array_fill_keys($hostParameters, true));
        $path = '/' . implode('/', RouteUrlSegments::render($route, $pathValues));

        return mb_strtolower($scheme) . '://' . $host . $path;
    }
}
