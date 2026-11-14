<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The fallback lane's coverage question: the longest collapsed prefix
 * that governs a missed identifier -- split from
 * {@see NamespaceFallbacks} as its own unit so the registry keeps its
 * registration vocabulary and the coverage walk keeps its longest-
 * prefix arithmetic.
 *
 * The walk rides the identifier's own governing-prefix chain
 * ({@see NamespaceSpellings::prefixesOf()}): every segment prefix the
 * identifier owns, shortest first, each looked up in the registry --
 * the last registered entry the chain finds is the longest one, so the
 * covering answer needs no length bookkeeping at all (the chain is
 * strictly lengthening by construction).
 */
final readonly class NamespaceCoverings
{
    /**
     * The covering entry the longest governing prefix answers, or null
     * when no prefix governs -- the registry's own consultation fold
     * ({@see NamespaceFallbacks::covering()}), the same walk answering
     * the entry it selected. An unregistered last prefix cannot be
     * selected (the walk only picks keys the map carries), so the
     * entry read never observes an absent key.
     *
     * @param array<string, array{factory: Closure(Container, string): mixed, lifetime: ServiceLifetime}> $fallbacks
     *
     * @return null|array{factory: Closure(Container, string): mixed, lifetime: ServiceLifetime}
     */
    public function entry(array $fallbacks, string $id): ?array
    {
        $longest = $this->longestPrefix($fallbacks, $id);

        if ($longest === null) {
            return null;
        }

        return $fallbacks[$longest];
    }

    /**
     * The sharing policy the covering entry carries -- the lifetime
     * probe's fallback half ({@see ContainerAnswers::lifetime()}), the
     * same coverage walk answering the policy the selected entry was
     * registered under, the one read that lets a declared alias to a
     * fallback-covered target ride the prefix's own policy ({@see
     * AliasLifetimes}). An identifier no prefix governs answers null.
     *
     * @param array<string, array{factory: Closure(Container, string): mixed, lifetime: ServiceLifetime}> $fallbacks
     */
    public function lifetime(array $fallbacks, string $id): ?ServiceLifetime
    {
        $entry = $this->entry($fallbacks, $id);

        if ($entry === null) {
            return null;
        }

        return $entry['lifetime'];
    }

    /**
     * The longest registered prefix governing the identifier, or null
     * when none does -- the entry and policy folds' own step, private
     * since the registry's consultation moved onto them (nothing
     * outside the unit asks for the prefix: the entry it selected, or
     * the policy it registered under, are the questions callers ask).
     *
     * @param array<string, mixed> $fallbacks the registered prefixes, keyed collapsed
     * @param string               $id        the missed identifier, in its own spelling
     */
    private function longestPrefix(array $fallbacks, string $id): ?string
    {
        $best = null;

        foreach (NamespaceSpellings::prefixesOf(NamespaceSpellings::collapsed($id)) as $prefix) {
            if (!\array_key_exists($prefix, $fallbacks)) {
                continue;
            }

            $best = $prefix;
        }

        return $best;
    }
}
