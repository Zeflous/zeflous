<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Container\Aot\AotArtifact;
use Zef\Framework\Container\Aot\AotOutcome;
use Zef\Framework\ContainerKnobs;

/**
 * The boot's reserved-service registrations and lazy marks: the four
 * read-side services both lanes register ahead of the definitions (so
 * a host's same-identifier entry is an explicit rebind that wins) --
 * the tags locator under {@see ContainerServices::TAGS_SERVICE}, the
 * boot's lane answer under {@see ContainerServices::AOT_SERVICE}, the
 * namespace layer's locator under
 * {@see ContainerServices::NAMESPACES_SERVICE}, and the debug
 * inventory under {@see ContainerServices::INVENTORY_SERVICE}, the
 * `framework.config` precedent -- plus the lazy registry's
 * post-install marking step both lanes share a name for ({@see
 * LazyMarking::marks()}).
 *
 * One spelling of each registration, shared by the lanes that register
 * them through their composites ({@see readSides} the compile lane's own,
 * {@see artifactReadSides} the artifact lane's) -- the individual
 * registrations are the composites' own internals.
 */
final readonly class ContainerRegistrations
{
    /**
     * The artifact lane's three read-side registrations in the order
     * the compile lane's own registers use: the baked tag index, the
     * namespace locator over the tree rebuilt from the baked
     * identifiers under the live knob the digest proved equal, and the
     * lane's own boot answer -- one spelling of the artifact lane's
     * registration half, so the lane itself keeps the install order
     * alone.
     */
    public static function artifactReadSides(
        Container $container,
        AotArtifact $aotArtifact,
        ContainerKnobs $containerKnobs,
    ): void {
        self::tagsLocator($container, ServiceTagIndex::fromIdsByTag($aotArtifact->tags()));

        self::namespacesLocator(
            $container,
            NamespaceTree::sealed(array_keys($aotArtifact->entries()), $containerKnobs->namespaces),
        );

        self::debugLocator($container, $containerKnobs->definitions);

        self::artifactOutcome($container, $aotArtifact);
    }

    /**
     * The compile lane's registration half in one spelling: the four
     * read-side services -- the tags locator over the planned index,
     * the namespace locator over the planned tree, the debug inventory
     * over the parsed declarations (the inventory answers what the host
     * spelled, the same answer whichever lane booted), and the lane's
     * own boot answer with the mode's development note -- so the lane
     * itself keeps the install order alone ({@see artifactReadSides},
     * the artifact lane's own composite spelling).
     */
    public static function readSides(
        Container $container,
        ContainerKnobs $containerKnobs,
        ContainerCompilePlan $containerCompilePlan,
        bool $bypassed,
    ): void {
        self::tagsLocator($container, $containerCompilePlan->tagIndex);

        self::namespacesLocator($container, $containerCompilePlan->namespaceTree);

        self::debugLocator($container, $containerKnobs->definitions);

        self::outcome(
            $container,
            AotOutcome::forTheLane(
                $containerCompilePlan,
                $containerKnobs->aot->enabled(),
                $bypassed,
                $containerKnobs->mode->isDevelopment(),
            ),
        );
    }

    /**
     * The degraded dump's re-registered boot answer: the render
     * refusal the compile lane answered by skipping the artifact
     * write -- the same-identifier rebind the container's own
     * last-wins registration discipline keeps, so the outcome a host
     * reads from the reserved service names the degradation instead of
     * the dump that never happened.
     *
     * @param ContainerCompilePlan $containerCompilePlan the plan the lane
     *                                                   compiled (the report and
     *                                                   the count ride along)
     */
    public static function degradedOutcome(Container $container, ContainerCompilePlan $containerCompilePlan): void
    {
        $container->set(
            ContainerServices::AOT_SERVICE,
            static fn (): AotOutcome => AotOutcome::artifactUnrenderable(
                $containerCompilePlan->unreferenced,
                \count($containerCompilePlan->definitions),
            ),
        );
    }

    /**
     * The tags lane's read side.
     */
    private static function tagsLocator(Container $container, ServiceTagIndex $serviceTagIndex): void
    {
        $container->set(
            ContainerServices::TAGS_SERVICE,
            static fn (Container $container): TaggedServices => new TaggedServices($serviceTagIndex, $container),
        );
    }

    /**
     * The namespace layer's read side: the locator over the boot's
     * sealed radix tree.
     */
    private static function namespacesLocator(Container $container, NamespaceRadixTree $namespaceRadixTree): void
    {
        $container->set(
            ContainerServices::NAMESPACES_SERVICE,
            static fn (Container $container): NamespacedServices => new NamespacedServices(
                $namespaceRadixTree,
                $container,
            ),
        );
    }

    /**
     * The debug inventory's read side (ROADMAP area 12): the parsed
     * declarations the boot spelled, captured as the inventory's own
     * snapshot, with the lazy marks read live through the registry
     * the resolution lanes consult -- one registration both lanes
     * take from their own knobs, so the inventory answers the same
     * declarations whichever lane booted.
     *
     * @param list<ServiceDefinition> $definitions the boot's parsed
     *                                             declaration set
     */
    private static function debugLocator(Container $container, array $definitions): void
    {
        $container->set(
            ContainerServices::INVENTORY_SERVICE,
            static fn (Container $container): ContainerInventory => new ContainerInventory(
                $definitions,
                $container->lazyIdentities(),
            ),
        );
    }

    /**
     * The compile/dump lane's boot answer.
     */
    private static function outcome(Container $container, AotOutcome $aotOutcome): void
    {
        $container->set(
            ContainerServices::AOT_SERVICE,
            static fn (): AotOutcome => $aotOutcome,
        );
    }

    /**
     * The artifact lane's boot answer: the loaded artifact's own
     * outcome, the unreferenced report and the carried entry count
     * riding along -- the lane's own registration to call, never a
     * surface of its own.
     */
    private static function artifactOutcome(Container $container, AotArtifact $aotArtifact): void
    {
        $container->set(
            ContainerServices::AOT_SERVICE,
            static fn (): AotOutcome => AotOutcome::artifactLoaded(
                $aotArtifact->unreferenced(),
                \count($aotArtifact->entries()),
            ),
        );
    }
}
