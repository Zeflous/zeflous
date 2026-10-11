<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Captures one request segment through a node's parameter edge.
 *
 * The capture spans exactly one non-empty segment (up to the next `/`
 * or the end of the path); empty segments (`//`, trailing `/`) capture
 * nothing, so the walk cannot capture across or into a separator. The
 * capture is undone when the branch below it fails, so the next
 * alternative starts from the same captured state.
 *
 * @internal
 */
final class SegmentCapture
{
    /**
     * Tries the node's parameter edge: capture one non-empty segment,
     * continue the walk from its end, undo the capture on failure.
     *
     * @param list<string> $captured the values captured so far (by reference)
     */
    public static function walkParameterEdge(
        RadixNode $radixNode,
        string $path,
        int $position,
        array &$captured,
        RouteMatcher $routeMatcher,
    ): ?CompiledRoute {
        $parameterChild = $radixNode->parameterChild();

        if (!$parameterChild instanceof RadixNode) {
            return null;
        }

        $boundary = mb_strpos($path, '/', $position);
        $end = $boundary === false ? mb_strlen($path) : $boundary;

        if ($end === $position) {
            return null;
        }

        $captured[] = mb_substr($path, $position, $end - $position);
        $route = $routeMatcher->walk($parameterChild, $path, $end, $captured);

        if ($route instanceof CompiledRoute) {
            return $route;
        }

        array_pop($captured);

        return null;
    }
}
