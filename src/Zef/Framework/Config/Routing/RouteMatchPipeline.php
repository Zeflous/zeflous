<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The match pipeline, in the engine's own order: the fold behind
 * {@see RadixTree::match()} -- the path walk settles candidates,
 * {@see RouteHostSelection} picks the highest-precedence host
 * bucket, {@see RouteVersionSelection} narrows by API version, and
 * {@see RouteMethodAnswer} judges the method. A version selection
 * that emptied while the path's candidates stand answers through the
 * miss fold's own method judgement ({@see RouteVersionMiss}, #794) --
 * the wrong method is named with its 405 and Allow regardless of the
 * version, and only a method the path answers leaves the version
 * itself as the fail-closed miss.
 *
 * The stages the order runs answer through {@see RouteMatchStages}
 * (the shared spelling the counting twin destructures too); this
 * fold is the hot path's thin reader of the result member -- the
 * tree owns the seams, the stages fold owns the sequence.
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
        return RouteMatchStages::run($radixNode, $method, $path, $host, $accept, $version)['result'];
    }
}
