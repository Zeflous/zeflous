<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The radix tree's seal-time compression walk: the bottom-up
 * elimination of single-child chains, split from
 * {@see NamespaceRadixTree} as its own unit the way the tree's other
 * walks are their own -- the compression is the seal's machinery, not
 * the tree's vocabulary.
 *
 * A node is eliminated when it is not the root, holds no identifiers,
 * and has exactly one child -- its edge label is absorbed into the
 * child (the `Zef` + `Framework` chain merges into one edge).
 * Children are keyed by the first segment of their (possibly
 * compound) label, so segment-wise traversal stays one array lookup
 * per node hop on the compressed shape; the iteration order the
 * build left is itself deterministic, and every reader either walks
 * the keys (order-free) or sorts what it collects.
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final readonly class NamespaceTreeCompression
{
    /**
     * Compresses the node graph bottom-up, answering the root of the
     * compressed shape.
     *
     * @param RadixNode $node  the root being compressed
     * @param string    $label the edge label the root answers to ('' -- the root has none)
     *
     * @return array{ids: array<string, string>, children: array<string, mixed>, label: string}
     *                                                                                          the compressed root
     */
    public function compress(array $node, string $label): array
    {
        $children = [];

        /**
         * The children are homogeneous with their parent -- the storage
         * type widens them, so re-assert the shape. A canonical-numeric
         * segment ('app.carrier.0') is stored by PHP as an int key, so
         * the key spelling this walk reads is int|string.
         *
         * @var array<int|string, RadixNode> $nodeChildren
         */
        $nodeChildren = $node['children'];

        foreach ($nodeChildren as $key => $child) {
            $compressed = $this->compress($child, (string) $key);
            $first = explode(NamespaceSpellings::SEPARATOR, $compressed['label'])[0];
            $children[$first] = $compressed;
        }

        $node['children'] = $children;

        if ($label !== '' && $node['ids'] === [] && \count($children) === 1) {
            $only = $children[array_key_first($children)];
            $only['label'] = $label . NamespaceSpellings::SEPARATOR . $only['label'];

            return $only;
        }

        $node['label'] = $label;

        return $node;
    }
}
