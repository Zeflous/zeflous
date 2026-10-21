<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\ContainerKnobs;

/**
 * The compile lane's dump half: the fresh artifact the full lane lays
 * down for the next boot ({@see ContainerCompileLane} keeps the
 * compile and install it dumps for).
 *
 * The dump is production's alone ({@see ContainerMode}): a development
 * boot never lays an artifact down -- the churn a next development
 * boot would only distrust again, and the fingerprint stays unpaid on
 * the lane that will not dump.
 *
 * A plan the artifact boundary cannot render degrades: the full lane
 * already answered this boot, so the honest answer is no artifact for
 * the next boot and the outcome note that names the degradation
 * ({@see AotUnrenderableException}) -- never a crashed boot for a
 * plan the boundary cannot carry.
 */
final readonly class ContainerDumpLane
{
    /**
     * Lays the fresh artifact down for the next boot, when the lane's
     * own posture may dump and a cache carries it -- and names the
     * outcome when the boundary cannot render the plan.
     */
    public function layDown(
        Container $container,
        ContainerCompilePlan $containerCompilePlan,
        ContainerKnobs $containerKnobs,
        ?ContainerAotCache $containerAotCache,
    ): void {
        if (!$containerKnobs->mayDump() || !$containerAotCache instanceof ContainerAotCache) {
            return;
        }

        if ($containerAotCache->dumpPlanOrDegrade($containerCompilePlan, $containerKnobs->fingerprint())) {
            return;
        }

        ContainerRegistrations::degradedOutcome($container, $containerCompilePlan);
    }
}
