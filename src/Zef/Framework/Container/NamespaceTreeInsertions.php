<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The radix tree's insert step: the grammar, the position dedup, and
 * the immutable walk one identifier's indexing owes -- split from
 * {@see NamespaceTreeBuilder} as its own unit so the builder keeps the
 * build state and the sealed-once discipline, and the insert itself
 * stays the fold it is.
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final readonly class NamespaceTreeInsertions
{
    /**
     * Indexes one identifier, answering the root and counters the
     * build carries forward.
     *
     * @param RadixNode             $root                  the root being indexed into
     * @param NamespaceTreeCounters $namespaceTreeCounters the build's counters
     * @param string                $id                    the identifier, in its own spelling
     *
     * @return array{0: RadixNode, 1: NamespaceTreeCounters} the indexed root and the recorded counters
     */
    public function inserted(array $root, NamespaceTreeCounters $namespaceTreeCounters, string $id): array
    {
        $namespaceTreeIndexing = new NamespaceTreeIndexing();
        [$segments, $collapsed] = new NamespaceIdentifierGrammar()->segmentsOf($id);
        $indexed = $namespaceTreeIndexing->spellingAt($root, $collapsed);

        if ($indexed !== null) {
            if ($indexed === $id) {
                return [$root, $namespaceTreeCounters];
            }

            throw NamespaceBuiltRefusals::positionCollision($indexed, $id);
        }

        return [
            $namespaceTreeIndexing->insertInto($root, $segments, $collapsed, $id),
            $namespaceTreeCounters->recorded($segments),
        ];
    }
}
