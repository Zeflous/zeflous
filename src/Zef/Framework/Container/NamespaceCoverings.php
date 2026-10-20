<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

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
     * The longest registered prefix governing the identifier, or null
     * when none does.
     *
     * @param array<string, mixed> $fallbacks the registered prefixes, keyed collapsed
     * @param string               $id        the missed identifier, in its own spelling
     */
    public function longestPrefix(array $fallbacks, string $id): ?string
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
