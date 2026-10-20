<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The radix tree's edge-label arithmetic: the label a child carries,
 * split back into segments, and the segment comparison a descent hop
 * reads -- split from {@see RadixTreeHop} as its own unit so the hop
 * keeps its three-way answer and the label vocabulary stays its own.
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final readonly class RadixEdgeLabels
{
    /**
     * The child's edge label, split back into segments (an absent label
     * falls back to the child key itself -- the uncompressed form).
     *
     * @param RadixNode $child the child being entered
     * @param string    $head  the child key, the fallback label
     *
     * @return list<string>
     */
    public static function segmentsOf(array $child, string $head): array
    {
        $label = $child['label'] ?? '';

        return $label !== '' ? explode(NamespaceSpellings::SEPARATOR, $label) : [$head];
    }

    /**
     * Whether the first count segments of the remaining query equal the
     * label's first count segments.
     *
     * @param list<string> $remaining the query segments still to consume
     * @param list<string> $label     the edge label's segments
     */
    public static function segmentsMatch(array $remaining, array $label, int $count): bool
    {
        for ($i = 0; $i < $count; ++$i) {
            if ($remaining[$i] !== $label[$i]) {
                return false;
            }
        }

        return true;
    }
}
