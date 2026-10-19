<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One dependency edge against the scope policy: the ungoverned and
 * public targets pass, the guarded subtree's own members pass, the
 * internal target refuses the outside consumer, and the module target
 * spends its budget -- split from {@see NamespaceScopes} as its own
 * unit so the walk keeps its machinery exemption and the edge keeps
 * its policy.
 */
final readonly class NamespaceScopeEdges
{
    /**
     * Enforces one dependency edge.
     *
     * @param string             $consumerId         the referencing service
     * @param string             $dep                the referenced target
     * @param NamespaceRadixTree $namespaceRadixTree the sealed tree
     * @param int                $budget             the cross-scope budget
     * @param CrossScopeBudget   $crossScopeBudget   the module kind's spend lane
     */
    public function enforce(
        string $consumerId,
        string $dep,
        NamespaceRadixTree $namespaceRadixTree,
        int $budget,
        CrossScopeBudget $crossScopeBudget,
    ): void {
        $target = $namespaceRadixTree->scopeOf($dep);

        if (!$target instanceof NamespaceScope || $target->kind === NamespaceScopeKinds::SCOPE_PUBLIC) {
            return;
        }

        if (NamespaceSpellings::governs($target->prefix, NamespaceSpellings::collapsed($consumerId))) {
            return;
        }

        if ($target->kind === NamespaceScopeKinds::SCOPE_INTERNAL) {
            throw NamespaceBuiltRefusals::internalViolation($consumerId, $dep, $target->prefix);
        }

        $crossScopeBudget->spend($consumerId, $dep, $namespaceRadixTree, $budget);
    }
}
