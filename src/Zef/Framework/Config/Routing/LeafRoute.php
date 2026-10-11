<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The route settled at a fully consumed node.
 *
 * A walk that exhausts the path ends here: the node's route answers
 * only when the captured values satisfy its constraints, so a
 * constraint failure makes this branch of the walk a miss, exactly like
 * a structural mismatch.
 *
 * @internal
 */
final class LeafRoute
{
    /**
     * The node's route when the captured values satisfy its constraints,
     * or null when the node holds no route or the constraints reject it.
     *
     * @param list<string> $captured
     */
    public static function accepting(RadixNode $radixNode, array $captured): ?CompiledRoute
    {
        $route = $radixNode->route();

        if (!$route instanceof CompiledRoute || !$route->accepts($captured)) {
            return null;
        }

        return $route;
    }
}
