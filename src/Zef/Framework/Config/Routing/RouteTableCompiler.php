<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Compiles a route table into a radix tree's parts: the
 * entries-to-tree orchestration behind {@see RadixTree::compile()}
 * -- the list check, the one fail-closed {@see
 * CompiledRoute::fromArray()} per entry, and the {@see
 * RouteTreeBuilder} wiring whose registry the reverse-routing index
 * carries away.
 *
 * The fold answers the tree's parts rather than the tree itself, so
 * the dependency stays one-directional: the tree assembles itself
 * over the parts, and the compiler never knows the tree's shape.
 * The parts carry the compiled entries in the table's own order
 * ({@see RadixTree::routes()}'s declaration order -- the order the
 * tree's inspection seams answer, never a re-derivation).
 *
 * @internal
 */
final class RouteTableCompiler
{
    /**
     * Compiles a route table into the tree's parts.
     *
     * @param array<mixed> $routes a list of Standard Route Schema entries
     *
     * @return array{root: RadixNode, namedRoutes: array<string, CompiledRoute>, routes: list<CompiledRoute>}
     */
    public static function compile(array $routes): array
    {
        if (!array_is_list($routes)) {
            throw new ConfigException('The route table must be a list of route entries.');
        }

        $compiledRoutes = [];

        foreach (array_keys($routes) as $index) {
            $compiledRoutes[] = CompiledRoute::fromArray($routes[$index]);
        }

        $routeTreeBuilder = new RouteTreeBuilder();

        return [
            'root' => $routeTreeBuilder->build($compiledRoutes),
            'namedRoutes' => $routeTreeBuilder->registry()->namedRoutes(),
            'routes' => $compiledRoutes,
        ];
    }
}
