<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The cross-scope budget's own parse: the integer, zero-or-more
 * validation the `max_cross_scope_refs` member owes -- split from
 * {@see NamespacesMap} as its own unit so the map's parse stays the
 * thin orchestrator and the budget's grammar stays testable.
 */
final readonly class NamespaceBudget
{
    /**
     * Parses the budget member, refusing the values it cannot carry.
     *
     * @param string $key    the knob's own key, for the refusals
     * @param mixed  $source the raw `max_cross_scope_refs` value
     */
    public function parsed(string $key, mixed $source): int
    {
        if ($source === null) {
            return 0;
        }

        if (!\is_int($source)) {
            throw NamespaceRefusals::maxRefsNotAnInteger($key, $source);
        }

        if ($source < 0) {
            throw NamespaceRefusals::maxRefsNegative($key, $source);
        }

        return $source;
    }
}
