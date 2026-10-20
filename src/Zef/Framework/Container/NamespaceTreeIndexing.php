<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The radix tree's immutable insert walk and its exact-position
 * lookup: the descent machinery behind {@see NamespaceRadixTree}'s
 * insert and containsExact, split as its own unit so the tree keeps
 * its public vocabulary and the walk keeps its path arithmetic.
 *
 * The walk descends the segment path, creating the missing nodes, and
 * records the identifier at the path's end; the lookup descends for an
 * exact identifier and answers the spelling the position carries.
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final readonly class NamespaceTreeIndexing
{
    /**
     * The exact-position lookup: the identifier spelling indexed at a
     * collapsed position, or null when the position carries none.
     *
     * @param RadixNode $node      the tree's root
     * @param string    $collapsed the identifier's collapsed spelling
     */
    public function spellingAt(array $node, string $collapsed): ?string
    {
        $result = RadixTreeNavigator::descendForLookup($node, NamespaceSpellings::segments($collapsed));

        return $result['ids'][$collapsed] ?? null;
    }

    /**
     * The immutable insert walk: descends the segment path, creating
     * the missing nodes, and records the identifier at the path's end.
     *
     * @param RadixNode    $node      the node being entered
     * @param list<string> $segments  the segments still to walk
     * @param string       $collapsed the identifier's collapsed spelling
     * @param string       $id        the identifier's own spelling
     *
     * @return RadixNode the entered node with the identifier recorded
     */
    public function insertInto(array $node, array $segments, string $collapsed, string $id): array
    {
        if ($segments === []) {
            $node['ids'][$collapsed] = $id;

            return $node;
        }

        $head = array_shift($segments);

        /**
         * The children are homogeneous with their parent (the node
         * layout {@see NamespaceRadixTree} documents), so re-assert the
         * shape the storage type widens away.
         *
         * @var array<string, RadixNode> $nodeChildren
         */
        $nodeChildren = $node['children'];
        $child = $nodeChildren[$head] ?? ['ids' => [], 'children' => []];
        $node['children'][$head] = $this->insertInto($child, $segments, $collapsed, $id);

        return $node;
    }
}
