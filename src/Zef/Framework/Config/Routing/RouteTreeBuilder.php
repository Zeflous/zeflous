<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Exception\InvariantViolationException;

/**
 * Builds the radix tree from validated routes.
 *
 * This is the compile-time writer of {@see RadixNode} (the only one that
 * exists): every route's pattern token stream (static runs and anonymous
 * parameter edges, see {@see RoutePatternTokens}) is inserted into the
 * tree, splitting any node whose prefix only partially matches, so shared
 * prefixes are stored once (the roadmap's radix compression: `/api` with
 * a single child `/v1` becomes one `/api/v1` run). Duplicate patterns
 * and names are rejected by {@see RouteTableRegistry} before anything is
 * inserted.
 *
 * All string arithmetic is character-based (`mb_*`), so multi-byte static
 * text splits and matches exactly like ASCII text.
 *
 * @internal
 */
final class RouteTreeBuilder
{
    private ?RouteTableRegistry $routeTableRegistry = null;

    /**
     * Builds the tree root holding every route.
     *
     * @param list<CompiledRoute> $routes
     */
    public function build(array $routes): RadixNode
    {
        $radixNode = new RadixNode('');
        $routeTableRegistry = new RouteTableRegistry();
        $this->routeTableRegistry = $routeTableRegistry;

        $routeNodeInserter = new RouteNodeInserter();

        foreach ($routes as $route) {
            $routeTableRegistry->register($route);
            $routeNodeInserter->insert($radixNode, RoutePatternTokens::of($route->path()), $route);
        }

        return $radixNode;
    }

    /**
     * The registration bookkeeping of the last build: the duplicate
     * rejection and the reverse-routing name index share one registry.
     */
    public function registry(): RouteTableRegistry
    {
        return $this->routeTableRegistry
            ?? throw new InvariantViolationException('The route tree has not been built yet.');
    }
}
