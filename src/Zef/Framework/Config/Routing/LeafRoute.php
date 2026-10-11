<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The routes settled at a fully consumed node.
 *
 * A walk that exhausts the path ends here: every route whose captured
 * values satisfy its constraints is a candidate, and the method answer
 * is chosen from them ({@see RouteMethodAnswer}). A route whose
 * constraints reject the captured values leaves the candidacy without
 * killing the node's other routes -- and when no candidate remains,
 * the branch is a miss, exactly like a structural mismatch.
 *
 * @internal
 */
final class LeafRoute
{
    /**
     * The routes settled at the node whose constraints accept the
     * captured values, in registration order; empty when the node holds
     * no route or every constraint rejects the values.
     *
     * @param list<string> $captured
     *
     * @return list<CompiledRoute>
     */
    public static function accepting(RadixNode $radixNode, array $captured): array
    {
        $candidates = [];

        foreach ($radixNode->routes() as $compiledRoute) {
            if (!$compiledRoute->accepts($captured)) {
                continue;
            }

            $candidates[] = $compiledRoute;
        }

        return $candidates;
    }
}
