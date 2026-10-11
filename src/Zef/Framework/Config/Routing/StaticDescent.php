<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Descends a node's static child for the next path character.
 *
 * The static child is tried before the parameter edge (longest shared
 * prefix first), and a miss here simply falls through to the parameter
 * alternative -- the descent itself is pure navigation.
 *
 * @internal
 */
final class StaticDescent
{
    /**
     * Walks the static child whose prefix continues the path at the
     * position: the routes the child's subtree settles, or none.
     *
     * @param list<string> $captured the values captured so far (by reference)
     *
     * @return list<CompiledRoute>
     */
    public static function descend(
        RadixNode $radixNode,
        string $path,
        int $position,
        array &$captured,
        RouteMatcher $routeMatcher,
    ): array {
        $child = $radixNode->staticChild(mb_substr($path, $position, 1));

        if (!$child instanceof RadixNode) {
            return [];
        }

        return $routeMatcher->walk($child, $path, $position, $captured);
    }
}
