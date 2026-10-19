<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The fallback registration's own grammar: the sharing policy and the
 * prefix's governability, the budget's slot accounting -- the checks a
 * registration owes before the registry accepts it -- split from
 * {@see NamespaceFallbacks} as its own unit (the grammar-versus-registry
 * boundary the tree's own builder draws).
 */
final readonly class NamespaceFallbackGrammar
{
    /** The hard bound on registered fallback prefixes. */
    public const int BUDGET = 64;

    /**
     * The registration's collapsed prefix, refusing the lifetimes, the
     * prefixes and the budget the registry cannot carry.
     *
     * @param string               $prefix          the raw prefix, in its own spelling
     * @param ServiceLifetime      $serviceLifetime the sharing policy the entry declared
     * @param array<string, mixed> $fallbacks       the registry's current prefixes, keyed collapsed
     */
    public function collapsed(string $prefix, ServiceLifetime $serviceLifetime, array $fallbacks): string
    {
        if ($serviceLifetime === ServiceLifetime::REQUEST) {
            throw NamespaceBuiltRefusals::fallbackRequestLifetime($prefix);
        }

        $collapsed = NamespaceSpellings::prefix($prefix);

        if ($collapsed === '') {
            throw NamespaceBuiltRefusals::fallbackRootPrefix($prefix);
        }

        if (!isset($fallbacks[$collapsed]) && \count($fallbacks) >= self::BUDGET) {
            throw NamespaceBuiltRefusals::fallbackBudgetExceeded(self::BUDGET);
        }

        return $collapsed;
    }
}
