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
     * position, or null when no static child starts there.
     *
     * @param list<string> $captured the values captured so far (by reference)
     */
    public static function descend(
        RadixNode $radixNode,
        string $path,
        int $position,
        array &$captured,
        RouteMatcher $routeMatcher,
    ): ?CompiledRoute {
        $child = $radixNode->staticChild(mb_substr($path, $position, 1));

        if (!$child instanceof RadixNode) {
            return null;
        }

        return $routeMatcher->walk($child, $path, $position, $captured);
    }
}
