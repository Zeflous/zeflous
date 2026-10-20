<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The sealed tree's scope queries: the nearest-ancestor walk over the
 * annotations map -- split from {@see NamespaceRadixTree} as its own
 * unit so the product keeps its index vocabulary and the annotation
 * walk keeps its longest-prefix arithmetic.
 *
 * The walk rides the identifier's own governing-prefix chain
 * ({@see NamespaceSpellings::prefixesOf()}): every segment prefix the
 * identifier owns, shortest first, each looked up in the annotations
 * map -- the last registered entry the chain finds is the longest one,
 * so the nearest answer needs no length bookkeeping at all (the chain
 * is strictly lengthening by construction).
 */
final readonly class NamespaceTreeScopes
{
    /**
     * The nearest annotation governing the collapsed identifier: the
     * longest governing prefix wins, the subtree root included.
     *
     * @param array<string, string> $annotations collapsed prefix => scope kind
     * @param string                $collapsed   the identifier's collapsed spelling
     */
    public function nearest(array $annotations, string $collapsed): ?NamespaceScope
    {
        $best = null;

        foreach (NamespaceSpellings::prefixesOf($collapsed) as $prefix) {
            if (!\array_key_exists($prefix, $annotations)) {
                continue;
            }

            $best = new NamespaceScope($prefix, $annotations[$prefix]);
        }

        return $best;
    }
}
