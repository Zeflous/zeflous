<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Container\Aot\AotArtifact;
use Zef\Framework\Container\Aot\AotDefinitions;
use Zef\Framework\Container\Aot\AotOutcome;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\ContainerKnobs;

/**
 * The artifact lane's own half of the boot: probe, install, warm -- the
 * work a digest-matched artifact answers the boot with, split from the
 * bootstrapper as its own unit the way the compile lane is, so the
 * orchestrator stays thin ({@see ContainerBootstrapper} keeps only the
 * knob parse, the seam guard and the lane choice).
 *
 * The probe loads the artifact the cache carries and compares its
 * digest against the live configuration's: a match installs the
 * artifact's closures through the container's own public surface and
 * answers true; a mismatch is *proven* staleness -- the artifact is
 * never run -- and answers false, leaving the fresh compile and the
 * fresh dump to the full lane.
 */
final readonly class AotBootLane
{
    /**
     * Whether the artifact answered the boot: probe, and on a digest
     * match install everything the artifact carries.
     */
    public function answers(
        Container $container,
        ContainerAotCache $containerAotCache,
        ContainerKnobs $containerKnobs,
    ): bool {
        $aotArtifact = $containerAotCache->load();

        if ($aotArtifact->digest() !== $containerKnobs->fingerprint()) {
            return false;
        }

        $this->install($container, $aotArtifact, $containerKnobs->aot->warm());

        return true;
    }

    /**
     * The artifact's install half: the baked tag index, the lane's own
     * boot answer, the entries, and the warmup -- in the order the
     * compile lane's own registers use.
     */
    private function install(Container $container, AotArtifact $aotArtifact, bool $warm): void
    {
        ContainerRegistrations::tagsLocator($container, ServiceTagIndex::fromIdsByTag($aotArtifact->tags()));

        ContainerRegistrations::outcome($container, AotOutcome::artifactLoaded(
            $aotArtifact->unreferenced(),
            \count($aotArtifact->entries()),
        ));

        AotDefinitions::install($container, $aotArtifact);

        if (!$warm) {
            return;
        }

        SingletonWarmer::warm($this->singletonIds($aotArtifact), $container);
    }

    /**
     * The singleton-lifetime identifiers the artifact's entries carry,
     * in declaration order.
     *
     * @param AotArtifact $aotArtifact the loaded artifact
     *
     * @return list<string>
     */
    private function singletonIds(AotArtifact $aotArtifact): array
    {
        $ids = [];

        foreach ($aotArtifact->entries() as $id => $entry) {
            if ($entry->lifetime !== ServiceLifetime::SINGLETON) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
