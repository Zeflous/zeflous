<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Container\Aot\AotArtifact;
use Zef\Framework\Container\Aot\AotDefinitions;
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

        $this->install($container, $aotArtifact, $containerKnobs);

        return true;
    }

    /**
     * The artifact's install half: the baked tag index, the namespace
     * locator over the rebuilt tree, the lane's own boot answer, the
     * entries, the lazy marks, the interception arming, and the warmup
     * -- in the order the compile lane's own registers use. The
     * interception materialises fresh from the live knobs every boot:
     * the artifact carries definitions (data), the interception
     * carries objects (the digest folds the knobs themselves, so a
     * changed set is a proven-stale artifact the boot never runs). The
     * namespace tree rebuilds from the artifact's baked identifiers
     * under the live knob the same digest proved equal -- the cheaper
     * spelling of the same tree ({@see NamespaceTree::sealed()} -- the
     * compile lane walks the planned set, this lane walks the baked
     * identifiers, the `@`-machinery exemption and the seal shared),
     * because the scope enforcement the compile lane ran was already
     * run over this exact definition set at the dump, under the exact
     * policy the digest just re-proved. The lazy map bakes instead of
     * rebuilding ({@see AotArtifact::lazy()}): the compile lane marks
     * from the *planned* set -- post-decoration, where a decorated
     * target's deferral has travelled to its re-homed `@decor:` base
     * identifier -- and the parsed declarations alone cannot re-derive
     * that walk.
     */
    private function install(Container $container, AotArtifact $aotArtifact, ContainerKnobs $containerKnobs): void
    {
        ContainerRegistrations::artifactReadSides($container, $aotArtifact, $containerKnobs->namespaces);

        // The debug inventory's declarations are the boot's parsed
        // set, the same answer the compile lane's own registration
        // takes ({@see ContainerRegistrations::debugLocator}).
        ContainerRegistrations::debugLocator($container, $containerKnobs->definitions);

        AotDefinitions::install($container, $aotArtifact);

        // The compile lane's own order ({@see
        // LazyMarking::marks()}): mark after the install loop, arm
        // before the warmup.
        foreach ($aotArtifact->lazy() as $id => $class) {
            $container->markLazy($id, $class);
        }

        ContainerArming::interceptions($container, $containerKnobs);

        if (!$containerKnobs->aot->warm()) {
            return;
        }

        SingletonWarmer::warm(ContainerSingletons::carried($aotArtifact), $container);
    }
}
