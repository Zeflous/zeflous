<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The tags lane's read side: the grouping questions a host asks over
 * the booted container (ROADMAP, "CONTAINER" area 6 -- a tag carries
 * no built-in meaning; the meaning belongs to the logic that reads
 * it, and this locator is that logic's one honest instrument).
 *
 * Registered at boot under the reserved {@see \Zef\Framework\FrameworkDefaults::TAGS_SERVICE}
 * identifier (`framework.tags`), built from the planned definition
 * set: the locator holds the boot-built {@see ServiceTagIndex} and
 * resolves the identifiers it names through the container on demand --
 * a service resolves exactly as its own declaration spells it
 * (lifetime, arguments, guards all unchanged), the failures of that
 * resolution propagating unchanged ({@see Container::get()} is the
 * fail-fast seam here, never bypassed).
 *
 * The lookup surface is deliberately four answers, no more:
 *
 * - {@see has()} / {@see ids()} -- the index questions;
 * - {@see services()} -- every carrier, resolved, in declaration
 *   order (the subscriber/voter/command collection pattern);
 * - {@see one()} -- exactly one carrier, resolved: the reference
 *   lineage answered "unused" with null and "several" with a
 *   distinct exception type from a second method -- two softer
 *   spellings of one concern. This lane answers one concern one
 *   way: absent is refused with the way out, several is refused
 *   naming every carrier.
 *
 * A tag spelled outside the grammar is refused with the grammar it
 * must match -- the same vocabulary {@see ServiceTags} enforces on
 * declarations, so a lookup can never silently disagree with what a
 * declaration was allowed to say.
 */
final readonly class TaggedServices
{
    public function __construct(
        private ServiceTagIndex $serviceTagIndex,
        private Container $container,
    ) {
    }

    /**
     * Whether any booted definition carries the tag.
     */
    public function has(string $tag): bool
    {
        return $this->ids($tag) !== [];
    }

    /**
     * The identifiers carrying the tag, in first-declaration order.
     *
     * @return list<string>
     */
    public function ids(string $tag): array
    {
        ServiceTags::assert($tag);

        return $this->serviceTagIndex->ids($tag);
    }

    /**
     * Every service carrying the tag, resolved through the container
     * in declaration order -- a resolution failure propagates
     * unchanged (a tagged service that cannot build is a defect to
     * surface, not a collection entry to skip).
     *
     * @return list<mixed>
     */
    public function services(string $tag): array
    {
        return array_map(
            $this->container->get(...),
            $this->ids($tag),
        );
    }

    /**
     * The exactly one service carrying the tag, resolved.
     *
     * The tag nobody carries is refused with the way out; the tag
     * several services carry is refused naming every one of them --
     * multiplicity that is expected belongs to {@see services()}.
     */
    public function one(string $tag): mixed
    {
        $ids = $this->ids($tag);
        if ($ids === []) {
            throw new DefinitionException(
                "The tag '{$tag}' is carried by no booted service "
                . '(declare one, or spell a tag the definitions carry).',
            );
        }

        if (\count($ids) > 1) {
            throw new DefinitionException(
                "The tag '{$tag}' is carried by " . \count($ids) . ' services ('
                . implode(', ', $ids) . '); use services() when multiplicity is expected.',
            );
        }

        return $this->container->get($ids[0]);
    }
}
