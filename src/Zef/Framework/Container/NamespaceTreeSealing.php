<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The radix tree's seal step: the compression, the annotation sort,
 * and the read-only product's construction -- split from
 * {@see NamespaceTreeBuilder} as its own unit so the builder keeps the
 * build state and the sealed-once discipline, and the seal itself
 * stays the fold it is.
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final readonly class NamespaceTreeSealing
{
    /**
     * Seals the build's state into the read-only tree.
     *
     * @param RadixNode             $root                  the uncompressed root
     * @param array<string, string> $annotations           collapsed prefix => scope kind, unsorted
     * @param NamespaceTreeCounters $namespaceTreeCounters the build's counters
     */
    public function sealed(
        array $root,
        array $annotations,
        NamespaceTreeCounters $namespaceTreeCounters,
    ): NamespaceRadixTree {
        $compressed = new NamespaceTreeCompression()->compress($root, '');
        ksort($annotations, \SORT_STRING);

        return new NamespaceRadixTree(
            $compressed,
            $annotations,
            $namespaceTreeCounters->serviceCount(),
            $namespaceTreeCounters->maxDepth(),
            $namespaceTreeCounters->rawSegments(),
        );
    }
}
