<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The parameters of a matched route: the walk's captured values zipped
 * with the route's own parameter names, in path order.
 *
 * The walk captures one value per parameter edge it consumed, in path
 * order -- the same order the grammar reads the pattern's parameter
 * names in -- so the zip is positional and needs no lookup: the first
 * captured value belongs to the first parameter, and so on.
 *
 * @internal
 */
final class RouteParameters
{
    /**
     * Zips the captured values with the route's parameter names.
     *
     * @param list<string> $captured one value per route parameter, in path order
     *
     * @return array<string, string>
     */
    public static function zip(CompiledRoute $compiledRoute, array $captured): array
    {
        $parameters = [];

        foreach ($compiledRoute->path()->parameterNames() as $index => $name) {
            $parameters[$name] = $captured[$index];
        }

        return $parameters;
    }
}
