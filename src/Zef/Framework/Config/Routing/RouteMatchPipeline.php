<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The match pipeline, in the engine's own order: the fold behind
 * {@see RadixTree::match()} -- the path walk settles candidates,
 * {@see RouteHostSelection} picks the highest-precedence host
 * bucket, {@see RouteVersionSelection} narrows by API version, and
 * {@see RouteMethodAnswer} judges the method. The tree owns the
 * seams; this fold owns the sequence between them.
 *
 * The counting twin of the same sequence is {@see
 * RouteMatchExplainer} -- the same folds, staged and counted, for
 * the development surface. This fold carries no counting: it is
 * the hot path the engine's own benchmarks pin, and every stage
 * hands its answer to the next without recording anything.
 *
 * @internal
 */
final class RouteMatchPipeline
{
    /**
     * Matches a request method, path and optional exact host against
     * the compiled tree, in the engine's own order.
     */
    public static function match(
        RadixNode $radixNode,
        string $method,
        string $path,
        ?string $host = null,
        ?string $accept = null,
        ?string $version = null,
    ): MatchResult {
        $captured = [];
        $routes = new RouteMatcher()->walk($radixNode, $path, 0, $captured);

        $requestHost = RouteHostRequest::canonicalise($host);
        $routes = RouteHostSelection::select($routes, $requestHost);
        $routes = RouteVersionSelection::select($routes, $version);

        if ($routes === []) {
            return MatchResult::notFound();
        }

        return RouteMethodAnswer::resolve($method, $routes, $captured, $requestHost, $accept);
    }
}
