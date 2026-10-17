<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

use Zef\Framework\Contracts\HealthCheckInterface;

/**
 * The health views' own vocabulary and membership rule: which probes
 * a requested group admits -- the unit the {@see HealthAggregator}
 * reads before it runs a single probe, so a probe outside the view
 * never runs at all (a probe runs once per scrape, and only in its
 * own view).
 *
 * THE GROUP SEMANTICS: the `live` view is the explicit declaration
 * alone (process-local truth -- never a dependency, a failure means
 * restart, not drain); the `ready` view is everything the live
 * declaration does not own (a plain check is a dependency probe,
 * i.e. readiness -- the grouping interface's own default); the
 * `all` view is every check; and any other view is the exact
 * declaration match.
 */
final class HealthGroups
{
    /** The process-local health view ({@see GroupedHealthCheckInterface}). */
    public const string GROUP_LIVE = 'live';

    /** The dependency-backed health view -- the plain check's own default group. */
    public const string GROUP_READY = 'ready';

    /** The every-probe view {@see HealthAggregator::aggregate()} reads. */
    public const string GROUP_ALL = 'all';

    /**
     * The probes belonging to the requested health view, in registry
     * order.
     *
     * @param list<HealthCheckInterface> $checks the registry's probe list
     * @param string                     $group  the health view being aggregated
     *
     * @return list<HealthCheckInterface>
     */
    public static function membersInGroup(array $checks, string $group): array
    {
        $inGroup = [];
        foreach ($checks as $check) {
            if (!self::memberIsInGroup($check, $group)) {
                continue;
            }

            $inGroup[] = $check;
        }

        return $inGroup;
    }

    /**
     * Answers whether the check belongs to the requested view: the
     * declared group for a grouped check, the ready default for a
     * plain one, judged by the view's own admission rule.
     */
    private static function memberIsInGroup(HealthCheckInterface $healthCheck, string $group): bool
    {
        $declared = $healthCheck instanceof GroupedHealthCheckInterface
            ? $healthCheck->healthGroup()
            : self::GROUP_READY;

        return self::admits($group, $declared);
    }

    /**
     * Answers whether the view admits the check's declared group:
     * every view admits its own exact declarations, the `all` view
     * admits every declaration, and the `ready` view -- asked on its
     * own, not by declaration -- admits every declaration the live
     * one does not own. The exact match answers first so the `live`
     * view's own declaration and the catch-all never overlap (a view
     * is its declaration's own complement's complement, not a
     * redundant branch the catch-all already spells).
     */
    private static function admits(string $group, string $declared): bool
    {
        if ($declared === $group) {
            return true;
        }

        if ($group === self::GROUP_ALL) {
            return true;
        }

        return $group === self::GROUP_READY && $declared !== self::GROUP_LIVE;
    }
}
