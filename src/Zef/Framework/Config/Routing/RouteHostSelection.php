<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Host precedence among the routes a path walk settled: exact hosts
 * outrank parameterised host patterns ({@see RouteHostPatternBucket}),
 * which outrank the host-agnostic fallback. A method mismatch at the
 * selected host specificity remains 405 rather than falling through, so
 * selection never mixes buckets.
 *
 * @internal
 */
final class RouteHostSelection
{
    /**
     * Selects the routes of the highest-precedence host bucket.
     *
     * @param list<CompiledRoute> $routes the settled candidates
     *
     * @return list<CompiledRoute> the selected bucket (possibly empty)
     */
    public static function select(array $routes, ?string $requestHost): array
    {
        $exactHostRoutes = [];
        $hostPatternBucket = new RouteHostPatternBucket();
        $hostAgnosticRoutes = [];

        foreach ($routes as $route) {
            $routeHost = $route->host();
            if ($routeHost === null) {
                $hostAgnosticRoutes[] = $route;

                continue;
            }

            $hostParameters = $route->hostParameters($requestHost);
            if ($hostParameters === null) {
                continue;
            }

            if ($hostParameters === []) {
                $exactHostRoutes[] = $route;

                continue;
            }

            $hostPatternBucket->offer($route, $routeHost);
        }

        if ($exactHostRoutes !== []) {
            return $exactHostRoutes;
        }

        return $hostPatternBucket->routes() !== [] ? $hostPatternBucket->routes() : $hostAgnosticRoutes;
    }
}
