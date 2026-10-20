<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The module scope's budget lane: the cross-scope reference accounting
 * behind the enforcement walk ({@see NamespaceScopes}), split as its
 * own unit the way the reference's own budget ledger was -- the
 * zero-budget deny, the pair grouping by each side's guarded prefix,
 * and the distinct-target spend against the ledger, one concern.
 *
 * A pair's budget counts DISTINCT targets: the same target referenced
 * twice (or through two consumers the pair groups) spends one slot --
 * the same distinct-target semantics the boot graph's own cross-module
 * budget uses.
 */
final readonly class CrossScopeBudget
{
    public function __construct(
        private CrossScopeEdgeLedger $crossScopeEdgeLedger,
    ) {
    }

    /**
     * Spends one module-scoped reference against the budget, refusing
     * the violations the policy owes.
     *
     * @param string             $consumerId         the outside consumer reaching in
     * @param string             $dep                the module-scoped target it references
     * @param NamespaceRadixTree $namespaceRadixTree the sealed tree (the consumer's
     *                                               own guarded prefix walks it)
     * @param int                $budget             the policy's cross-scope budget
     */
    public function spend(
        string $consumerId,
        string $dep,
        NamespaceRadixTree $namespaceRadixTree,
        int $budget,
    ): void {
        if ($budget <= 0) {
            throw NamespaceBuiltRefusals::moduleBudgetZero($consumerId, $dep);
        }

        $namespaceGuardedScopes = new NamespaceGuardedScopes();
        $annotations = $namespaceRadixTree->annotations();
        $pair = $this->crossScopeEdgeLedger->pairFor(
            $namespaceGuardedScopes->guarded($annotations, NamespaceSpellings::collapsed($consumerId)),
            $namespaceGuardedScopes->guarded($annotations, NamespaceSpellings::collapsed($dep)),
        );

        if (!$this->crossScopeEdgeLedger->isNewEdge($pair, $dep)) {
            return;
        }

        if ($this->crossScopeEdgeLedger->countFor($pair) >= $budget) {
            throw NamespaceBuiltRefusals::moduleBudgetExceeded($pair, $budget);
        }

        $this->crossScopeEdgeLedger->record($pair, $dep);
    }
}
