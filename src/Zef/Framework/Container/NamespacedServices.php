<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The namespace layer's read side: the namespace questions a host asks
 * over the booted container (ROADMAP, "CONTAINER" area 9's namespace
 * half -- the reference container's `NamespaceLayer` questions,
 * registered the way the tags lane registers its own read side).
 *
 * Registered at boot under the reserved
 * {@see \Zef\Framework\FrameworkDefaults::NAMESPACES_SERVICE}
 * identifier (`framework.namespaces`), built from the boot's sealed
 * radix tree: the locator holds the tree and resolves the identifiers
 * it names through the container on demand -- a service resolves
 * exactly as its own declaration spells it (lifetime, arguments,
 * guards all unchanged), the failures of that resolution propagating
 * unchanged ({@see Container::get()} is the fail-fast seam here, never
 * bypassed).
 *
 * The lookup surface is deliberately four answers, no more:
 *
 * - {@see idsUnder()} -- the index question: every identifier whose
 *   namespace path lies under the prefix;
 * - {@see servicesUnder()} -- the batch answer: every identifier under
 *   the prefix, resolved, keyed by the identifier's own spelling;
 * - {@see scopeOf()} -- the governance question: the nearest annotation
 *   that governs the identifier, null when ungoverned;
 * - {@see stats()} -- the tree's own measurements (the introspection
 *   the area's debugging story owes the host).
 *
 * The identifiers the tree carries are the boot's planned set -- the
 * declarative surface. A closure-registered identifier
 * ({@see Container::set()}) never enters the tree, so the namespace
 * questions cannot see it: the same honest boundary the tree's whole
 * family carries, spelled here where the host reads it.
 */
final readonly class NamespacedServices
{
    public function __construct(
        private NamespaceRadixTree $namespaceRadixTree,
        private Container $container,
    ) {
    }

    /**
     * Every indexed identifier under the namespace prefix, sorted
     * deterministically -- the spelling each identifier declared.
     *
     * @return list<string>
     */
    public function idsUnder(string $prefix): array
    {
        return $this->namespaceRadixTree->idsUnderPrefix($prefix);
    }

    /**
     * Every service under the namespace prefix, resolved through the
     * container and keyed by the identifier's own spelling -- a
     * resolution failure propagates unchanged (a namespaced service
     * that cannot build is a defect to surface, not a collection entry
     * to skip).
     *
     * @return array<string, mixed>
     */
    public function servicesUnder(string $prefix): array
    {
        $ids = $this->namespaceRadixTree->idsUnderPrefix($prefix);

        return array_combine($ids, array_map($this->container->get(...), $ids));
    }

    /**
     * The nearest scope annotation governing the identifier, or null
     * when ungoverned (public by convention).
     */
    public function scopeOf(string $id): ?NamespaceScope
    {
        return $this->namespaceRadixTree->scopeOf($id);
    }

    /**
     * The sealed tree's own measurements.
     *
     * @return array{
     *     serviceIds: int, nodes: int, edges: int, maxDepth: int,
     *     rawSegments: int, compressionRatio: float, annotations: int, sealed: bool
     * }
     */
    public function stats(): array
    {
        return $this->namespaceRadixTree->stats();
    }
}
