<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Walks a compiled radix tree for one request path.
 *
 * The walk is character-based and depth-first: a node's static prefix is
 * consumed ({@see PrefixConsumption}), then its static child whose
 * prefix starts with the current character is tried first
 * ({@see StaticDescent} -- longest shared prefix), then the anonymous
 * parameter edge ({@see SegmentCapture}), with full backtracking. An
 * exhausted path settles on the node's routes -- every route whose
 * constraints accept the captured values ({@see LeafRoute}) -- and the
 * method answer is chosen from them by {@see RouteMethodAnswer}.
 *
 * The captured values travel by reference through the recursion only;
 * the public entry returns the candidate routes together with their
 * captured values as one immutable tuple, so no state is shared between
 * walks.
 *
 * @internal
 */
final class RouteMatcher
{
    /**
     * Walks the tree from a node at the given character offset.
     *
     * This is the matcher's single public seam: {@see RadixTree::match()}
     * starts the walk at the root and owns the tuple the result carries.
     *
     * @param list<string> $captured the values captured so far (by reference)
     *
     * @return list<CompiledRoute> the constraint-accepting routes settled
     *                             at the fully consumed position, empty
     *                             when the path misses
     */
    public function walk(RadixNode $radixNode, string $path, int $offset, array &$captured): array
    {
        $position = PrefixConsumption::positionAfterPrefix($radixNode, $path, $offset);

        if ($position === null) {
            return [];
        }

        if ($position === mb_strlen($path)) {
            return LeafRoute::accepting($radixNode, $captured);
        }

        $routes = StaticDescent::descend($radixNode, $path, $position, $captured, $this);

        return $routes !== []
            ? $routes
            : SegmentCapture::walkParameterEdge($radixNode, $path, $position, $captured, $this);
    }
}
