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
    public static function resolve(string $method, array $routes, array $captured): MatchResult
    {
        $route = self::select($method, $routes);

        if (!$route instanceof CompiledRoute) {
            return MatchResult::methodNotAllowed(self::allowedMethods($routes));
        }

        return MatchResult::found($route, self::parameters($route, $captured));
    }

    /**
     * The candidate route that answers the request method, in
     * registration order, or null when none does.
     *
     * @param list<CompiledRoute> $routes
     */
    private static function select(string $method, array $routes): ?CompiledRoute
    {
        foreach ($routes as $route) {
            if ($route->answersMethod($method)) {
                return $route;
            }
        }

        return null;
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

    /**
     * Zips the selected route's parameter names with the captured values.
     *
     * @param list<string> $captured
     *
     * @return array<string, string>
     */
    private static function parameters(CompiledRoute $compiledRoute, array $captured): array
    {
        $parameters = [];

        foreach ($compiledRoute->path()->parameterNames() as $index => $name) {
            $parameters[$name] = $captured[$index];
        }

        return $parameters;
    }
}
