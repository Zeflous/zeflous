<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The module-scope budget's ledger: the distinct (consumer => target)
 * cross-scope targets one enforcement walk has already spent -- the
 * reference container's own ledger, carried unchanged because the
 * semantics it keeps are the walk's whole accounting.
 *
 * A pair's budget counts DISTINCT targets: the same target referenced
 * twice (or through two consumers the pair groups) spends one slot,
 * the honest reading of "how many services has this consumer reached
 * into the module for" -- the same distinct-target semantics the boot
 * graph's own cross-module budget uses.
 */
final class CrossScopeEdgeLedger
{
    /** @var array<string, array<string, true>> pair => the distinct targets spent */
    private array $recorded = [];

    /**
     * The pair's own spelling: each side's guarded prefix with its
     * trailing separator trimmed, joined by the arrow the violation
     * message reads -- one spelling of the key the budget groups by.
     */
    public function pairFor(string $consumerPrefix, string $targetPrefix): string
    {
        return mb_rtrim($consumerPrefix, NamespaceSpellings::SEPARATOR)
            . '=>'
            . mb_rtrim($targetPrefix, NamespaceSpellings::SEPARATOR);
    }

    /**
     * Whether the pair has not yet spent this target -- the value the
     * record wrote is the spend marker itself, read back here.
     */
    public function isNewEdge(string $pair, string $target): bool
    {
        return !($this->recorded[$pair][$target] ?? false);
    }

    /**
     * Spends the target against the pair.
     */
    public function record(string $pair, string $target): void
    {
        $this->recorded[$pair][$target] = true;
    }

    /**
     * How many distinct targets the pair has spent.
     */
    public function countFor(string $pair): int
    {
        return \count($this->recorded[$pair] ?? []);
    }
}
