<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Host precedence among the routes a path walk settled: the matching host
 * claims compete in one specificity-ordered bucket
 * ({@see RouteHostPatternBucket}), and the host-agnostic routes remain the
 * fallback when no claim matched. An exact host is simply the most specific
 * claim possible -- every label literal, a specificity equal to the label
 * count, which no co-matching pattern can reach (a pattern with the same
 * label count spends at least one label on a wildcard) -- so "exact hosts
 * outrank patterns" needs no separate bucket: the ordering is the
 * specificity. A method mismatch at the selected specificity remains 405
 * rather than falling through, so selection never mixes claims of different
 * specificity.
 *
 * @internal
 */
final class RouteHostSelection
{
    /**
     * Selects the routes of the highest-precedence host claim.
     *
     * @param list<CompiledRoute> $routes the settled candidates
     *
     * @return list<CompiledRoute> the selected bucket (possibly empty)
     */
    public static function select(array $routes, ?string $requestHost): array
    {
        $routeHostPatternBucket = new RouteHostPatternBucket();
        $hostAgnosticRoutes = [];

        foreach ($routes as $route) {
            $routeHost = $route->host();
            if ($routeHost === null) {
                $hostAgnosticRoutes[] = $route;

                continue;
            }

            if ($route->hostParameters($requestHost) === null) {
                continue;
            }

            $routeHostPatternBucket->offer($route, $routeHost);
        }

        if ($routeHostPatternBucket->routes() !== []) {
            return $routeHostPatternBucket->routes();
        }

        return $hostAgnosticRoutes;
    }
}
