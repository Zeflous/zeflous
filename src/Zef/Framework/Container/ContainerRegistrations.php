<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Container\Aot\AotArtifact;
use Zef\Framework\Container\Aot\AotOutcome;

/**
 * The boot's reserved-service registrations: the two read-side services
 * both lanes register ahead of the definitions (so a host's
 * same-identifier entry is an explicit rebind that wins) -- the tags
 * locator under {@see ContainerServices::TAGS_SERVICE} and the boot's
 * lane answer under {@see ContainerServices::AOT_SERVICE}, the
 * `framework.config` precedent.
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
     * riding along.
     */
    public static function artifactOutcome(Container $container, AotArtifact $aotArtifact): void
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
