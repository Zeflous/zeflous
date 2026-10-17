<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

use Closure;

/**
 * Runs the registered health checks and aggregates them into one
 * scrape -- the metrics family's health engine over this
 * repository's already-live seam ({@see HealthChecker}'s own probe
 * registry), exactly where the campaign's lane record reserved the
 * arrival.
 *
 * A probe that crashes degrades instead of failing the scrape
 * ({@see HealthProbe}'s own containment), and the scrape as a whole
 * is bounded ({@see self::aggregate()}'s overall deadline): once the
 * wall-clock budget has passed, the remaining probes are marked down
 * with the skip marker instead of extending the response -- a
 * hanging sync probe can still hold its own slot, but the response
 * as a whole can no longer block forever, the property the load
 * balancers polling `/health` depend on. The clock behind the
 * deadline is {@see HealthScrapeClock}, the injectable monotonic
 * seam the suite freezes exactly.
 *
 * THE ZERO-PROBE POSTURE IS DEGRADED, NEVER GREEN: a requested group
 * whose probe list is empty is a wiring failure (a health registry
 * that collected nothing), and a green `/health` that never ran a
 * single check is fail-open -- the scrape answers degraded with the
 * explicit marker check, so the JSON and the Prometheus output both
 * carry the signal.
 *
 * This repository's boolean probe seam carries no message, so the
 * reference's message-bearing result contract is not ported beside
 * it: the messages the scrape's JSON carries are the judgement's own
 * diagnostics. The group membership ({@see HealthGroups}), the probe
 * judgement ({@see HealthProbe}), the name grammar ({@see ProbeName})
 * and the report's exposition rendering ({@see HealthExposition})
 * are the scrape's own units, each one named place the suite reads
 * directly.
 *
 * Ported from the reference implementation (legacy
 * `zef-framework-src` `Application/Observability/HealthAggregator.php`):
 * the group semantics (a plain check reads as `ready`; `live` is the
 * explicit opt-in), the overall-deadline skip, the crash containment,
 * the tookMs reporting and the JSON rendering move verbatim over the
 * boolean seam. The port's deviations: the probe list comes from the
 * live {@see HealthChecker} registry (the reference collected its own
 * indicator list), the clock is the injectable monotonic seam, the
 * probe contract is this repository's own, and the Prometheus
 * rendering lives in the scrape's own render unit -- the deviations
 * the campaign's lane record spells.
 */
final readonly class HealthAggregator
{
    /** The monotonic clock the scrape's deadline and elapsed readings share. */
    private HealthScrapeClock $healthScrapeClock;

    /**
     * @param HealthChecker        $healthChecker the probe registry the scrape runs
     * @param null|(Closure():int) $monotonicNs   the monotonic clock seam,
     *                                            nanoseconds; defaults to the
     *                                            system monotonic clock
     */
    public function __construct(
        private HealthChecker $healthChecker,
        ?Closure $monotonicNs = null,
    ) {
        $this->healthScrapeClock = new HealthScrapeClock($monotonicNs);
    }

    /**
     * Aggregates every registered probe under an overall deadline.
     *
     * @param null|int $overallTimeoutMs the wall-clock budget for the whole
     *                                   scrape, milliseconds; null is unlimited
     *
     * @return array{status:string,checks:list<array{name:string,status:string,message:string}>,tookMs:float}
     */
    public function aggregate(?int $overallTimeoutMs = null): array
    {
        return $this->runGrouped(HealthGroups::GROUP_ALL, $overallTimeoutMs);
    }

    /**
     * Aggregates one health view: the `live` declaration alone, or
     * everything it does not own (`ready`), or every probe (`all`),
     * or the exact match of any other declared group.
     *
     * @param string   $group            the health view to aggregate
     * @param null|int $overallTimeoutMs the wall-clock budget for the scrape
     *
     * @return array{status:string,checks:list<array{name:string,status:string,message:string}>,tookMs:float}
     */
    public function aggregateFor(string $group, ?int $overallTimeoutMs = null): array
    {
        return $this->runGrouped($group, $overallTimeoutMs);
    }

    /**
     * The grouped scrape itself: the probes for the requested view,
     * each judged under the deadline, the status math over their
     * outcomes.
     *
     * @param string   $group            the health view to aggregate
     * @param null|int $overallTimeoutMs the wall-clock budget for the scrape
     *
     * @return array{status:string,checks:list<array{name:string,status:string,message:string}>,tookMs:float}
     */
    private function runGrouped(string $group, ?int $overallTimeoutMs): array
    {
        $started = $this->healthScrapeClock->now();
        $deadline = $this->healthScrapeClock->deadline($started, $overallTimeoutMs);

        $checks = HealthGroups::membersInGroup($this->healthChecker->checks(), $group);
        if ($checks === []) {
            return HealthOutcome::zeroProbes($group, $this->healthScrapeClock->elapsedMs($started));
        }

        $probeChecks = [];
        foreach ($checks as $check) {
            $probeChecks[] = HealthProbe::judged($check, $deadline, $this->healthScrapeClock);
        }

        return HealthOutcome::fromProbeChecks($probeChecks, $this->healthScrapeClock->elapsedMs($started));
    }
}
