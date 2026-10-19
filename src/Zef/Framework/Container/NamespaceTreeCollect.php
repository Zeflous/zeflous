<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The radix tree's collection walk: every identifier under a node, in
 * the node's own spelling -- the enumeration behind
 * {@see NamespaceRadixTree::idsUnderPrefix()}, split as its own unit so
 * the tree keeps its query vocabulary and the walk keeps its traversal.
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final readonly class NamespaceTreeCollect
{
    /**
     * Collects every identifier under the node.
     *
     * @param RadixNode    $node the node being collected
     * @param list<string> $out  the collector, by reference
     */
    public function collect(array $node, array &$out): void
    {
        foreach ($node['ids'] as $id) {
            $out[] = $id;
        }

        /**
         * @var array<string, RadixNode> $nodeChildren
         */
        $nodeChildren = $node['children'];

        foreach ($nodeChildren as $nodeChild) {
            $this->collect($nodeChild, $out);
        }
    }
}
