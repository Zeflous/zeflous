<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The method-not-allowed answer's payload: every method the routes
 * answer, upper-cased, de-duplicated and sorted.
 *
 * Extracted from {@see RouteMethodAnswer}'s own private walk (#794)
 * because the version miss's method judgement
 * ({@see RouteVersionMiss}) answers the same 405 with the same
 * payload over the pre-narrowing candidate set -- one vocabulary for
 * one spelling, the allowed-methods list a route family's routes
 * answer, whoever asks.
 *
 * @internal
 */
final class RouteAllowedMethods
{
    /**
     * @param list<CompiledRoute> $routes the routes whose methods answer
     *
     * @return list<string> the allowed methods, sorted
     */
    public static function of(array $routes): array
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
