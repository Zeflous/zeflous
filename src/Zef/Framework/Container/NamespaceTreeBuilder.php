<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The namespace radix tree's mutable build phase: the insert walk, the
 * scope annotations, and the seal that answers the read-only
 * {@see NamespaceRadixTree} -- split from the product as its own unit
 * the way the interception registry's arming is its own, so what the
 * boot serves is born sealed and what built it is finished.
 *
 * Sealed once: an insert or annotate after the seal is the honest
 * refusal ({@see NamespaceBuiltRefusals}) -- exactly the posture the
 * interception registry's arming takes ({@see Interceptions}). The
 * build's own grammar ({@see NamespaceIdentifierGrammar},
 * {@see NamespaceAnnotationGrammar}) and its counters
 * ({@see NamespaceTreeCounters}) are their own units; the walk itself
 * ({@see NamespaceTreeIndexing}) and the compression
 * ({@see NamespaceTreeCompression}) already are.
 *
 * @phpstan-type RadixNode array{ids: array<string, string>, children: array<string, mixed>, label?: string}
 */
final class NamespaceTreeBuilder
{
    /** @var RadixNode */
    private array $root = ['ids' => [], 'children' => []];

    /** @var array<string, string> collapsed prefix => scope kind */
    private array $annotations = [];

    private NamespaceTreeCounters $namespaceTreeCounters;

    private bool $sealed = false;

    public function __construct()
    {
        $this->namespaceTreeCounters = new NamespaceTreeCounters();
    }

    /**
     * Indexes one identifier's namespace position.
     *
     * Idempotent for the same identifier; a DIFFERENT identifier that
     * collapses onto a position already indexed is the collision the
     * tree refuses to serve silently -- two spellings of one namespace
     * position are two services the prefix enumeration could never
     * answer honestly between.
     */
    public function insert(string $id): void
    {
        if ($this->sealed) {
            throw NamespaceBuiltRefusals::sealedInsert();
        }

        [$this->root, $this->namespaceTreeCounters] = new NamespaceTreeInsertions()->inserted(
            $this->root,
            $this->namespaceTreeCounters,
            $id,
        );
    }

    /**
     * Annotates a namespace subtree with a scope kind -- the
     * nearest-ancestor annotation wins at query time, so an inner
     * `public` annotation can reopen what an outer `internal` one
     * closed.
     *
     * Re-annotating a prefix with the same kind is the idempotent
     * no-op; re-annotating it with a different kind is the disagreement
     * the tree refuses.
     */
    public function annotate(string $prefix, string $scope): void
    {
        if ($this->sealed) {
            throw NamespaceBuiltRefusals::sealedAnnotate();
        }

        $collapsed = new NamespaceAnnotationGrammar()->collapsedOf($prefix, $scope);
        $known = $this->annotations[$collapsed] ?? null;

        if ($known !== null && $known !== $scope) {
            throw NamespaceBuiltRefusals::annotateKindDisagreement($collapsed, $known, $scope);
        }

        $this->annotations[$collapsed] = $scope;
    }

    /**
     * Seals the build: the compression runs, the annotations sort, and
     * the read-only tree answers -- the builder refusing any further
     * build step afterwards ({@see NamespaceBuiltRefusals::alreadySealed()}).
     */
    public function seal(): NamespaceRadixTree
    {
        if ($this->sealed) {
            throw NamespaceBuiltRefusals::alreadySealed();
        }

        $this->sealed = true;

        return new NamespaceTreeSealing()->sealed(
            $this->root,
            $this->annotations,
            $this->namespaceTreeCounters,
        );
    }
}
