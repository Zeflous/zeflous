<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Inserts one route's token stream into the tree.
 *
 * The two token kinds are two edge kinds: a parameter token descends the
 * node's anonymous parameter edge (creating it when absent); a static
 * run is delegated to {@see StaticRunInserter}, which descends and
 * splits the static children so shared prefixes are stored exactly once.
 * An exhausted stream settles the route on the node it reached
 * (beside the method-disjoint routes that may already be there).
 *
 * @internal
 */
final class RouteNodeInserter
{
    /**
     * Consumes the token stream from a node onwards.
     *
     * @param list<array{bool, string}> $tokens
     */
    public function insert(RadixNode $radixNode, array $tokens, CompiledRoute $compiledRoute): void
    {
        if ($tokens === []) {
            $radixNode->addRoute($compiledRoute);

            return;
        }

        [$isParameter, $text] = $tokens[0];

        if ($isParameter) {
            $parameterChild = $radixNode->parameterChild() ?? new RadixNode('');
            $radixNode->setParameterChild($parameterChild);
            $this->insert($parameterChild, \array_slice($tokens, 1), $compiledRoute);

            return;
        }

        new StaticRunInserter()->insertRun(
            $radixNode,
            $text,
            \array_slice($tokens, 1),
            $compiledRoute,
            $this,
        );
    }
}
