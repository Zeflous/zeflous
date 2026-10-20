<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\ContainerKnobs;

/**
 * The full lane's own half of the boot: the multi-pass pipeline over
 * the parsed knob set, the two reserved read-side services, the install
 * loop, the warmup, and -- when the lane's knob is on -- the fresh
 * artifact laid down for the next boot. Split from the bootstrapper as
 * its own unit so the orchestrator stays thin, the same split the
 * artifact lane takes ({@see AotBootLane}).
 *
 * The lane it ran under rides along: a proven-stale artifact set aside
 * (the bypass the probe answered false to) names itself in the boot
 * answer, the one observable difference between the bypassed compile
 * and the ordinary one.
 */
final readonly class ContainerCompileLane
{
    public function __construct(
        private ContainerCompilePipeline $containerCompilePipeline = new ContainerCompilePipeline(),
        private ContainerInstalls $containerInstalls = new ContainerInstalls(),
    ) {
    }

    /**
     * Compiles the knob set's definitions and installs them, answering
     * the booted container.
     *
     * @param bool $bypassed whether a proven-stale artifact was set aside
     *                       before this compile ran
     */
    public function install(
        Container $container,
        ContainerKnobs $containerKnobs,
        ?ContainerAotCache $containerAotCache,
        bool $bypassed,
    ): Container {
        $containerCompilePlan = $this->containerCompilePipeline->compile(
            $containerKnobs->definitions,
            $container,
            $containerKnobs->parameters->values(),
            $containerKnobs->autoconfig,
            $containerKnobs->contextual,
            $containerKnobs->decorators,
            $containerKnobs->namespaces,
        );

        // The four reserved read-side services in the lane's own
        // composite spelling ({@see ContainerRegistrations::readSides})
        // -- the same registration half the artifact lane spells
        // through its own composite.
        ContainerRegistrations::readSides($container, $containerKnobs, $containerCompilePlan, $bypassed);

        $this->containerInstalls->definitions($container, $containerCompilePlan->definitions);

        // Mark the lazy identities after the install loop ({@see
        // LazyMarking::marks()}): the resolution lanes consult the
        // registry from the first resolution on.
        LazyMarking::marks($container, $containerCompilePlan->definitions);

        // Arm before the warmup ({@see ContainerArming}): an eagerly
        // constructed singleton rides the interception like any other
        // construction.
        ContainerArming::interceptions($container, $containerKnobs);

        if ($containerKnobs->aot->warm()) {
            SingletonWarmer::warm(ContainerSingletons::planned($containerCompilePlan->definitions), $container);
        }

        // The dump is production's alone ({@see ContainerMode}): a
        // development boot never lays an artifact down -- the churn a
        // next development boot would only distrust again, and the
        // fingerprint stays unpaid on the lane that will not dump.
        if ($containerKnobs->mayDump() && $containerAotCache instanceof ContainerAotCache) {
            $containerAotCache->dumpPlan($containerCompilePlan, $containerKnobs->fingerprint());
        }

        return $container;
    }
}
