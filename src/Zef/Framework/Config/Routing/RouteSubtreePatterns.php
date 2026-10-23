<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The patterns of a subtree, breadth-first and capped: every route
 * settled at or beneath a node, parameter edges included (they are
 * what lives past the node), each spelled as its pattern with its
 * host claim when it carries one -- the candidate collection the
 * neighborhood reports.
 *
 * The breadth-first order is deliberate: the shallowest (closest)
 * spellings arrive first. The cap is the collection's one bound,
 * paid at the first route that would exceed it -- the walk ends
 * there, so the answer is exactly the budget, never a node more: a
 * neighborhood is a hint, not an inventory, and a hint that grows
 * with the table would grow the header it renders into.
 *
 * @internal
 */
final class RouteSubtreePatterns
{
    /** The candidate budget: the most patterns a collection reports. */
    private const int MAX_PATTERNS = 8;

    /**
     * Collects the capped, breadth-first patterns of a subtree: the
     * queue drains from the front as the walk visits, the children
     * the visited node owns enqueue behind -- the shallowest
     * spellings arrive first -- and the walk ends the moment the
     * budget is reached.
     *
     * @return list<string>
     */
    public static function collect(RadixNode $radixNode): array
    {
        $patterns = [];
        $queue = [$radixNode];

        while ($queue !== []) {
            $node = array_shift($queue);

            foreach ($node->routes() as $compiledRoute) {
                if (\count($patterns) === self::MAX_PATTERNS) {
                    return $patterns;
                }

                $patterns[] = self::spelling($compiledRoute);
            }

            foreach ($node->staticChildren() as $child) {
                $queue[] = $child;
            }

            $parameterChild = $node->parameterChild();
            if ($parameterChild === null) {
                continue;
            }

            $queue[] = $parameterChild;
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
