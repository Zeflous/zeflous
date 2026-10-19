<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * Segment-wise descent across the compressed edges of a
 * {@see NamespaceRadixTree}: the two query flavours over the one
 * shared hop, split as its own unit so the tree keeps its
 * data-structure responsibilities and the descents keep their walks
 * ({@see RadixTreeHop} owns the hop's edge-label arithmetic).
 *
 * The two flavours -- exact-identifier lookup and prefix enumeration --
 * walk the same compressed edges and disagree only on how a query that
 * ends MID-EDGE is treated: a lookup that stops mid-edge answers from
 * the deepest node it fully consumed, while a prefix that ends
 * mid-edge owns the whole edge subtree beneath it when the query
 * segments match the label so far (and nothing at all when they do
 * not).
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final readonly class RadixTreeNavigator
{
    /**
     * Descends along the segments for an exact-identifier lookup: the
     * deepest node the query fully consumed -- the node whose ids the
     * exact-identifier check then runs against.
     *
     * @param RadixNode    $root     the tree's root node
     * @param list<string> $segments the collapsed identifier's segments
     *
     * @return RadixNode the deepest fully-consumed node
     */
    public static function descendForLookup(array $root, array $segments): array
    {
        $node = $root;
        $remaining = $segments;
        $radixTreeHop = new RadixTreeHop();

        while ($remaining !== []) {
            [$kind, $child, $remaining] = $radixTreeHop->hop($node, $remaining);

            if ($kind !== 'descend') {
                // 'mid-match', 'mid-mismatch' or 'miss': no full edge was
                // consumed for the rest of the query -- the walk stays
                // where the last full edge left it.
                break;
            }

            $node = $child;
        }

        return $node;
    }

    /**
     * Descends to the node owning everything under a collapsed prefix:
     * the subtree's own node, or null when no edge path spells the
     * prefix -- the mid-edge mismatch answers null the same way a
     * missing edge does, because a prefix that disagrees with an edge
     * owns nothing beneath it.
     *
     * @param RadixNode    $root     the tree's root node
     * @param list<string> $segments the collapsed prefix's segments
     *
     * @return null|RadixNode the subtree's own node, or null
     */
    public static function descendToPrefix(array $root, array $segments): ?array
    {
        $node = $root;
        $remaining = $segments;
        $radixTreeHop = new RadixTreeHop();

        while ($remaining !== []) {
            [$kind, $child, $remaining] = $radixTreeHop->hop($node, $remaining);

            if ($kind === 'miss' || $kind === 'mid-mismatch') {
                return null;
            }

            if ($kind === 'mid-match') {
                // The prefix ends mid-edge with the label agreeing so
                // far: the whole edge subtree is under the prefix.
                return $child;
            }

            $node = $child;
        }

        return $node;
    }
}
