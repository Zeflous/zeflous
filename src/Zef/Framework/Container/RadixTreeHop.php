<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One descent hop across the compressed edges: the edge-label matching
 * machinery both of the navigator's query flavours ride, split from
 * {@see RadixTreeNavigator} as its own unit so the navigator keeps its
 * two descents and the hop keeps the label arithmetic.
 *
 * The hop resolves the child under the head segment and answers how
 * much of its (possibly compound) edge label the remaining query can
 * consume: fully consumed ('descend'), the query ending mid-edge
 * ('mid-match' when the partial label agreed, 'mid-mismatch' when it
 * did not), or no edge at all ('miss') -- the kind carries everything
 * the two flavours disagree on, so the arms answer exactly the data
 * their readers read.
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final readonly class RadixTreeHop
{
    /**
     * One descent hop.
     *
     * @param RadixNode              $node      the node being left
     * @param non-empty-list<string> $remaining the segments still to consume
     *
     * The answer's arms, in order: ['descend', child, unconsumed tail];
     * ['mid-match', child, tail]; ['mid-mismatch', child, tail]; or
     * ['miss', node, tail]
     *
     * @return array{0: string, 1: RadixNode, 2: list<string>}
     */
    public function hop(array $node, array $remaining): array
    {
        $head = $remaining[0];

        /**
         * The children are homogeneous with their parent (the node
         * layout {@see NamespaceRadixTree} documents), so re-assert the
         * node shape the storage type widens away.
         *
         * @var array<string, RadixNode> $nodeChildren
         */
        $nodeChildren = $node['children'];
        $child = $nodeChildren[$head] ?? null;

        if (\is_array($child)) {
            $label = RadixEdgeLabels::segmentsOf($child, $head);
            $labelCount = \count($label);

            if (\count($remaining) < $labelCount) {
                $matched = RadixEdgeLabels::segmentsMatch($remaining, $label, \count($remaining));

                return [$matched ? 'mid-match' : 'mid-mismatch', $child, $remaining];
            }

            if (RadixEdgeLabels::segmentsMatch($remaining, $label, $labelCount)) {
                return ['descend', $child, \array_slice($remaining, $labelCount)];
            }
        }

        // No edge at all, or a full-length label mismatch: the shared
        // miss shape -- no edge taken.
        return ['miss', $node, $remaining];
    }
}
