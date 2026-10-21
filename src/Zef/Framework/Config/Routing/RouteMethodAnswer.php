<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The method answer of a matched leaf: which of the routes settled at
 * the consumed node answers the request method.
 *
 * Several routes may share one pattern -- the registry admits them only
 * with disjoint method sets -- so the leaf's candidates are narrowed by
 * method here, and a path that matched while no candidate answers the
 * method reports every method those candidates do answer, sorted.
 *
 * @internal
 */
final class RouteMethodAnswer
{
    /**
     * Resolves the match of a walked path: the candidate answering the
     * request method with its parameters zipped from the captured
     * values, or the method-not-allowed answer carrying every method
     * the candidates do answer.
     *
     * @param list<CompiledRoute> $routes   the constraint-accepting candidates
     * @param list<string>        $captured the values the walk captured
     */
    public static function resolve(string $method, array $routes, array $captured, ?string $host = null): MatchResult
    {
        foreach ($routes as $route) {
            if ($route->answersMethod($method)) {
                $parameters = RouteParameters::zip($route, $captured);
                $hostParameters = $route->hostParameters($host) ?? [];

                return MatchResult::found($route, array_merge($parameters, $hostParameters));
            }
        }

        return MatchResult::methodNotAllowed(self::allowedMethods($routes));
    }

    /**
     * Every method the candidates answer, upper-cased, de-duplicated and
     * sorted -- the method-not-allowed answer of a matched path.
     *
     * @param list<CompiledRoute> $routes
     *
     * @return list<string>
     */
    private static function allowedMethods(array $routes): array
    {
        $allowedMethods = [];

        foreach ($routes as $route) {
            foreach ($route->methods() as $method) {
                $allowedMethods[$method] = $method;
            }
        }

        sort($allowedMethods, \SORT_STRING);

        return $allowedMethods;
    }
}
