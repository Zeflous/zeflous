<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The deepest static descent of one request path into the tree: the
 * static-only view of the walk the neighborhood reads -- a node's
 * prefix consumed character for character ({@see
 * PrefixConsumption}, the walk's own arithmetic, multi-byte exact),
 * then the static child whose run continues the path.
 *
 * No parameter edges are taken and no backtracking is performed,
 * because the descent's question is not "which route matches" (the
 * matcher answered that: none) but "how far did the request's own
 * text track the table". A run the path only partially continues
 * still counts its shared head ({@see SharedPrefixLength}, the tree
 * builder's own measure) and still names that run's node: the
 * developer who typed `/users` against a `/users/{id}` table learns
 * both facts they can act on. A path the node's own run already
 * exhausted stops there -- the child lookup past the path's end
 * answers none.
 *
 * @internal
 */
final class RouteMatchDescent
{
    /**
     * The descent from the tree's root.
     *
     * @return array{RadixNode, int} the node the descent stopped at
     *                               and the character offset the path
     *                               tracked to reach it
     */
    public static function deepest(RadixNode $radixNode, string $path): array
    {
        $node = $radixNode;
        $offset = 0;

        while (true) {
            $position = PrefixConsumption::positionAfterPrefix($node, $path, $offset);

            if ($position === null) {
                return [$node, $offset + SharedPrefixLength::of($node->prefix(), mb_substr($path, $offset))];
            }

            $offset = $position;

            $child = $node->staticChild(mb_substr($path, $offset, 1));

            if (!$child instanceof RadixNode) {
                return [$node, $offset];
            }

            $node = $child;
        }
    }
}
