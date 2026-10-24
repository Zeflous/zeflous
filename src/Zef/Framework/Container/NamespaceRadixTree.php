<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The namespace layer's radix tree (ROADMAP, "CONTAINER" area 9's
 * namespace half -- the reference container's `NamespaceRadixTree`,
 * adapted to the collapsed dual-separator spelling): the SEALED,
 * read-only product over the booted service identifiers' namespace
 * segments -- path-compressed, query-only, born sealed
 * ({@see NamespaceTreeBuilder} owns the mutable build phase; the
 * reference's freeze-time seal, by construction).
 *
 * Node layout (plain arrays):
 *
 *   node = ['ids' => array<string,string>, 'children' => array<string,node>, 'label' => string]
 *
 * The `ids` map keys the COLLAPSED identifier (see
 * {@see NamespaceSpellings}) and carries the identifier's own spelling
 * as its value -- the spelling the locator resolves through the
 * container, answered back by the prefix enumeration. The `label` key
 * holds the (possibly compound, `Framework.Container`) edge label the
 * seal's path compression left; children are keyed by the FIRST segment
 * of their label, so segment-wise traversal stays one array lookup per
 * node hop.
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final readonly class NamespaceRadixTree
{
    /**
     * @param RadixNode             $root        the compressed, sealed root
     * @param array<string, string> $annotations collapsed prefix => scope kind, ksorted
     */
    public function __construct(
        private array $root,
        private array $annotations,
        private int $serviceCount,
        private int $maxDepth,
        private int $rawSegments,
    ) {
    }

    /**
     * Every indexed identifier whose namespace path lies under the
     * prefix (segment-boundary semantics, the trailing separator
     * normalised), in the identifiers' own spellings, sorted
     * deterministically.
     *
     * @return list<string>
     */
    public function idsUnderPrefix(string $prefix): array
    {
        $collapsed = NamespaceSpellings::prefix($prefix);

        $node = RadixTreeNavigator::descendToPrefix(
            $this->root,
            NamespaceSpellings::segments(mb_rtrim($collapsed, NamespaceSpellings::SEPARATOR)),
        );

        if ($node === null) {
            return [];
        }

        $out = [];
        new NamespaceTreeCollect()->collect($node, $out);
        sort($out, \SORT_STRING);

        return $out;
    }

    /**
     * The nearest scope annotation for the identifier -- the longest
     * collapsed prefix that governs it, the subtree root included -- or
     * null when unannotated (public by convention, the answer the
     * enforcement walk treats as ungoverned).
     */
    public function scopeOf(string $id): ?NamespaceScope
    {
        return new NamespaceTreeScopes()->nearest($this->annotations, NamespaceSpellings::collapsed($id));
    }

    /**
     * The annotations map itself: collapsed prefix => scope kind, the
     * spelling the boot's enforcement walk and the locator's scope
     * answers share.
     *
     * @return array<string, string>
     */
    public function annotations(): array
    {
        return $this->annotations;
    }

    /**
     * The tree's own measurements -- the introspection the locator
     * surfaces (how many identifiers, how many nodes and edges the
     * compression left, the compression ratio it earned).
     *
     * @return array{
     *     serviceIds: int, nodes: int, edges: int, maxDepth: int,
     *     rawSegments: int, compressionRatio: float, annotations: int, sealed: bool
     * }
     */
    public function stats(): array
    {
        $population = new NamespaceTreeStats()->population($this->root);
        $edges = $population['edges'];

        return [
            'serviceIds' => $this->serviceCount,
            'nodes' => $population['nodes'],
            'edges' => $edges,
            'maxDepth' => $this->maxDepth,
            'rawSegments' => $this->rawSegments,
            'compressionRatio' => $edges > 0 ? round($this->rawSegments / $edges, 4) : 1.0,
            'annotations' => \count($this->annotations),
            'sealed' => true,
        ];
    }
}
