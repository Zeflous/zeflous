<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The namespace scope enforcement walk (the reference container's
 * `RadixTreeCompilerPass::enforceScopes()`, adapted to the boot graph
 * the pipeline already proves): the boot-time policy gate that reads
 * the planned set's dependency edges against the sealed tree's scope
 * annotations -- namespace governance needing no manual module tagging,
 * the subtree's own position decides ({@see NamespaceScopeEdges} owns
 * the per-edge policy).
 *
 * The container's own machinery (`@`-prefixed rewiring definitions --
 * the decorator re-homes, the contextual aliases, the value services)
 * is exempt as a CONSUMER: the machinery's edges are the boot's own
 * surgery, not a host declaration the policy governs. The machinery as
 * a TARGET is ungoverned by construction (it never enters the public
 * tree), so an edge into it passes the same way every ungoverned edge
 * does. Edges into closure-registered services are the same honest
 * boundary the whole tree carries: the declarative surface is what the
 * policy governs.
 */
final readonly class NamespaceScopes
{
    /**
     * Enforces the scope policy over the planned set's dependency
     * edges.
     *
     * @param list<ServiceDefinition> $planned            the pipeline's planned set
     * @param Container               $container          the container the edges resolve against
     * @param NamespaceRadixTree      $namespaceRadixTree the sealed tree the annotations answer from
     * @param NamespacesMap           $namespacesMap      the parsed scope policy (the budget's carrier)
     */
    public static function enforce(
        array $planned,
        Container $container,
        NamespaceRadixTree $namespaceRadixTree,
        NamespacesMap $namespacesMap,
    ): void {
        $crossScopeBudget = new CrossScopeBudget(new CrossScopeEdgeLedger());
        $budget = $namespacesMap->maxCrossScopeRefs();
        $namespaceScopeEdges = new NamespaceScopeEdges();

        foreach (DefinitionEdges::collect($planned, $container) as $consumerId => $targets) {
            if (str_starts_with($consumerId, '@')) {
                continue;
            }

            foreach ($targets as $target) {
                $namespaceScopeEdges->enforce($consumerId, $target, $namespaceRadixTree, $budget, $crossScopeBudget);
            }
        }
    }
}
