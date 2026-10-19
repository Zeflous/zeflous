<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Container\Aot\AotArtifact;
use Zef\Framework\Container\Aot\AotOutcome;

/**
 * The boot's reserved-service registrations: the three read-side
 * services both lanes register ahead of the definitions (so a host's
 * same-identifier entry is an explicit rebind that wins) -- the tags
 * locator under {@see ContainerServices::TAGS_SERVICE}, the boot's
 * lane answer under {@see ContainerServices::AOT_SERVICE}, and the
 * namespace layer's locator under
 * {@see ContainerServices::NAMESPACES_SERVICE}, the `framework.config`
 * precedent.
 *
 * One spelling of each registration, shared by the lanes that register
 * them ({@see AotBootLane}, {@see ContainerCompileLane}).
 */
final readonly class ContainerRegistrations
{
    /**
     * The tags lane's read side.
     */
    public static function tagsLocator(Container $container, ServiceTagIndex $serviceTagIndex): void
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
    public static function namespacesLocator(Container $container, NamespaceRadixTree $namespaceRadixTree): void
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
        NamespacesMap $namespacesMap,
    ): void {
        self::tagsLocator($container, ServiceTagIndex::fromIdsByTag($aotArtifact->tags()));

        self::namespacesLocator(
            $container,
            NamespaceTree::sealed(array_keys($aotArtifact->entries()), $namespacesMap),
        );

        self::artifactOutcome($container, $aotArtifact);
    }

    /**
     * The compile/dump lane's boot answer.
     */
    public static function outcome(Container $container, AotOutcome $aotOutcome): void
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
