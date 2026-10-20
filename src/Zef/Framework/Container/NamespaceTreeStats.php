<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The radix tree's measurements: the node-and-edge population walk
 * behind {@see NamespaceRadixTree::stats()}, split as its own unit so
 * the tree keeps its query vocabulary and the counting stays the pure
 * read of the compressed shape it walks.
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final readonly class NamespaceTreeStats
{
    /**
     * The node and edge population under the root.
     *
     * @param RadixNode $node the root being counted
     *
     * @return array{nodes: int, edges: int} the population, counted
     */
    public function population(array $node): array
    {
        $nodes = 0;
        $edges = 0;
        $this->countNodes($node, $nodes, $edges);

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Counts the node and edge population under the node.
     *
     * @param RadixNode $node  the node being counted
     * @param int       $nodes the node counter, by reference
     * @param int       $edges the edge counter, by reference
     */
    private function countNodes(array $node, int &$nodes, int &$edges): void
    {
        ++$nodes;

        /**
         * @var array<string, RadixNode> $nodeChildren
         */
        $nodeChildren = $node['children'];

        foreach ($nodeChildren as $nodeChild) {
            ++$edges;
            $this->countNodes($nodeChild, $nodes, $edges);
        }
    }
}
