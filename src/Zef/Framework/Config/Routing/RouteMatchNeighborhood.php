<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The route table's neighborhood of one request path: the deepest
 * node the path's static text still descends into, and the patterns
 * that live under it -- the "what you probably meant" a path-stage
 * miss explains itself with.
 *
 * The descent is deliberately the static-only view of the walk: a
 * node's prefix is consumed character for character ({@see
 * PrefixConsumption}, the walk's own arithmetic, multi-byte exact),
 * then the static child whose run continues the path is entered --
 * no parameter edges are taken and no backtracking is performed,
 * because the neighborhood's question is not "which route matches"
 * (the matcher answered that: none) but "how far did the request's
 * own text track the table, and what lives past that point". A run
 * the path only partially continues still counts its shared head
 * ({@see SharedPrefixLength}, the tree builder's own measure) and
 * still names the run's subtree as the neighborhood: the developer
 * who typed `/users` against a `/users/{id}` table learns both
 * facts they can act on. A parameter edge IS part of what lives
 * past the point, so the subtree the neighborhood reports includes
 * them -- as candidate patterns, not as descent steps.
 *
 * The candidates are the subtree's patterns, collected breadth-first
 * so the shallowest (closest) spellings arrive first, and capped at
 * {@see self::MAX_PATTERNS} -- a neighborhood is a hint, not an
 * inventory, and a hint that grows with the table would grow the
 * header it renders into.
 *
 * @internal
 */
final class RouteMatchNeighborhood
{
    /** The candidate budget: the most patterns a neighborhood reports. */
    private const int MAX_PATTERNS = 8;

    /**
     * Reads the neighborhood of a request path from the tree's root.
     *
     * @return array{prefix: string, patterns: list<string>} the deepest
     *                                                       static prefix the path tracked, verbatim from the path, and
     *                                                       the capped, breadth-first patterns under it
     */
    public static function around(RadixNode $root, string $path): array
    {
        [$node, $offset] = self::deepestDescent($root, $path);

        return [
            'prefix' => mb_substr($path, 0, $offset),
            'patterns' => self::patterns($node),
        ];
    }

    /**
     * The static descent from the root: consume each node's prefix
     * while it continues the path, then enter the static child whose
     * run continues the path -- stopping where a run the path only
     * partially continues ends (the shared head counted, that run's
     * node named), at the path's own end, or where no static child
     * continues.
     *
     * @return array{RadixNode, int} the node the descent stopped at
     *                               and the character offset the path tracked to reach it
     */
    private static function deepestDescent(RadixNode $radixNode, string $path): array
    {
        $node = $radixNode;
        $offset = 0;

        while (true) {
            $position = PrefixConsumption::positionAfterPrefix($node, $path, $offset);

            if ($position === null) {
                return [$node, $offset + SharedPrefixLength::of($node->prefix(), mb_substr($path, $offset))];
            }

            $offset = $position;

            if ($offset >= mb_strlen($path)) {
                return [$node, $offset];
            }

            $child = $node->staticChild(mb_substr($path, $offset, 1));

            if ($child === null) {
                return [$node, $offset];
            }

            $node = $child;
        }
    }

    /**
     * The patterns of a subtree, breadth-first and capped: every
     * route settled at or beneath the node, parameter edges included
     * (they are what lives past the node), each spelled as its
     * pattern with its host claim when it carries one.
     *
     * @return list<string>
     */
    private static function patterns(RadixNode $radixNode): array
    {
        $patterns = [];
        $queue = [$radixNode];

        for ($index = 0; $index < \count($queue) && \count($patterns) < self::MAX_PATTERNS; ++$index) {
            $node = $queue[$index];

            foreach ($node->routes() as $compiledRoute) {
                if (\count($patterns) >= self::MAX_PATTERNS) {
                    break;
                }

                $patterns[] = self::spelling($compiledRoute);
            }

            foreach ($node->staticChildren() as $child) {
                $queue[] = $child;
            }

            $parameterChild = $node->parameterChild();

            if ($parameterChild !== null) {
                $queue[] = $parameterChild;
            }
        }

        return $patterns;
    }

    /**
     * One candidate's spelling: the pattern verbatim, with the host
     * claim appended when the route carries one -- the two facts a
     * developer matching a request against the table reads first.
     */
    private static function spelling(CompiledRoute $compiledRoute): string
    {
        $pattern = $compiledRoute->path()->pattern();
        $host = $compiledRoute->host();

        return $host === null ? $pattern : $pattern . ' @' . $host;
    }
}
