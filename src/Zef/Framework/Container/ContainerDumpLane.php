<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Container\Aot\AotException;
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
 * plan the boundary cannot carry. The digest refusal takes the same
 * posture ({@see AotException} -- a source configuration the
 * fingerprint cannot fold is a configuration the artifact cannot
 * honestly carry either, the digest's own doctrine): the degradation
 * is computed *before* the plan-level answer because the fingerprint
 * is the first thing the dump asks for (#792 -- the argument-order
 * crash the audit caught: the fingerprint was evaluated in the same
 * expression that carried the degrade, so the refusal escaped the
 * lane before the degrade could answer it).
 */
final readonly class ContainerDumpLane
{
    /**
     * Lays the fresh artifact down for the next boot, when the lane's
     * own posture may dump and a cache carries it -- and names the
     * outcome when the boundary cannot render the plan, the digest
     * refusal degrading exactly the render refusal does (#792).
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

        // The digest first, inside its own refusal boundary: the
        // fingerprint is the one computation whose refusal this
        // lane degrades on, so it must never ride as an argument
        // into an expression whose own catch would answer too late
        // (#792) -- and the environmental refusals the dump below
        // still raises stay the operator's to fix, not caught here.
        try {
            $digest = $containerKnobs->fingerprint();
        } catch (AotException) {
            ContainerRegistrations::digestDegradedOutcome($container, $containerCompilePlan);

            return;
        }

        if ($containerAotCache->dumpPlanOrDegrade($containerCompilePlan, $digest)) {
            return;
        }

        ContainerRegistrations::degradedOutcome($container, $containerCompilePlan);
    }
}
