<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Merge;

/**
 * Deep array merge for the configuration layer's precedence layering
 * (Default < App < Env, later layers win -- see `ROADMAP.md`,
 * "Configuration System + DSL + Radix Tree").
 *
 * The merge rule is deliberately narrow and mirrors how configuration is
 * authored: a string key that addresses a map on *both* sides recurses, so
 * a layer can override one deep setting without re-declaring its siblings;
 * everything else -- a scalar, `null`, a list, a type change, or a key the
 * base does not carry yet -- is replaced wholesale by the later layer. A
 * list is therefore a full declaration ("list replace, never concat"): the
 * later layer's list *is* the merged list, and offsets the base carried are
 * dropped instead of being merged offset-by-offset. An empty array counts
 * as a list (`array_is_list([])` is true), so a layer that sets a key to
 * `[]` clears it rather than silently inheriting the base's entries.
 *
 * Integer-keyed entries inside a map follow the same rule: the entry is
 * replaced, never merged, so a mixed map (string and integer keys) merges
 * as a map at its string keys while its integer-keyed entries behave like
 * the lists they emulate. Key order is deterministic: base keys keep their
 * positions, replaced values stay at the base key's position, and keys new
 * to the later layer are appended in the later layer's order.
 *
 * The inputs are never modified (PHP copy-on-write separates the merged
 * array on first write), which keeps the merge safe to call on the arrays
 * an immutable configuration repository was built from.
 */
final readonly class ArrayMerger
{
    /**
     * Merges the layers from the lowest to the highest precedence; with no
     * layers at all the merge is an empty array.
     *
     * @param array<array-key, mixed> ...$layers
     *
     * @return array<array-key, mixed>
     */
    public static function merge(array ...$layers): array
    {
        $merged = [];

        foreach ($layers as $layer) {
            $merged = self::mergeLayer($merged, $layer);
        }

        return $merged;
    }

    /**
     * Overlays the layer onto the base, later layer wins.
     *
     * Recursion applies only to a string key that addresses a map on both
     * sides -- the existing value must be an array (a missing key or a
     * scalar/null base is replaced, never recursed into), the incoming value
     * must be an array too, and neither side may be a list: a list on either
     * side is a full declaration and replaces its counterpart wholesale.
     * Integer-keyed entries therefore never recurse -- they are replaced like
     * the list offsets they emulate.
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $layer
     *
     * @return array<array-key, mixed>
     */
    private static function mergeLayer(array $base, array $layer): array
    {
        $merged = $base;

        foreach (array_keys($layer) as $key) {
            if (
                \is_string($key)
                && isset($merged[$key])
                && \is_array($merged[$key])
                && \is_array($layer[$key])
                && !array_is_list($merged[$key])
                && !array_is_list($layer[$key])
            ) {
                $merged[$key] = self::mergeLayer($merged[$key], $layer[$key]);

                continue;
            }

            $merged = array_replace($merged, [$key => $layer[$key]]);
        }

        return $merged;
    }
}
