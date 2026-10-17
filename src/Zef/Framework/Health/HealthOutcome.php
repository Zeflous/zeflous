<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

/**
 * The health scrape's aggregate outcome: the report the probes'
 * judgements fold into -- the unit the {@see HealthAggregator}
 * answers, extracted so the scrape's orchestration and the status
 * math stay one named place each.
 *
 * THE FOLD IS THE STATUS MATH: a scrape is `ok` when every probe
 * answered up, `degraded` when any probe failed, crashed or was
 * skipped -- the one judgement the load balancer's 200/503 gates on.
 * THE ZERO-PROBE POSTURE IS DEGRADED, NEVER GREEN: a requested group
 * whose probe list is empty is a wiring failure (a health registry
 * that collected nothing), and a green scrape that never ran a
 * single check is fail-open -- the outcome answers degraded with the
 * explicit marker check, so the JSON and the Prometheus rendering
 * both carry the signal.
 */
final class HealthOutcome
{
    /**
     * The report the probes' judgements fold into: the aggregate
     * status over their outcomes, the checks verbatim, the elapsed
     * milliseconds the scrape took.
     *
     * @param list<array{name:string,status:string,message:string}> $probeChecks the probes' judged records
     * @param float                                                 $tookMs      the scrape's elapsed milliseconds
     *
     * @return array{status:string,checks:list<array{name:string,status:string,message:string}>,tookMs:float}
     */
    public static function fromProbeChecks(array $probeChecks, float $tookMs): array
    {
        $degraded = array_any(
            $probeChecks,
            static fn (array $probeCheck): bool => $probeCheck['status'] !== HealthStatuses::STATUS_UP,
        );

        return [
            'status' => $degraded ? HealthStatuses::STATUS_DEGRADED : HealthStatuses::STATUS_OK,
            'checks' => $probeChecks,
            'tookMs' => $tookMs,
        ];
    }

    /**
     * The zero-probe report: degraded, with the marker check carrying
     * the signal both renderings read -- a green scrape that never
     * ran a probe is fail-open.
     *
     * @param string $group  the health view that collected nothing
     * @param float  $tookMs the scrape's elapsed milliseconds
     *
     * @return array{status:string,checks:list<array{name:string,status:string,message:string}>,tookMs:float}
     */
    public static function zeroProbes(string $group, float $tookMs): array
    {
        return [
            'status' => HealthStatuses::STATUS_DEGRADED,
            'checks' => [[
                'name' => 'health.indicators',
                'status' => HealthStatuses::STATUS_DOWN,
                'message' => "no health indicators registered for group '" . $group . "'",
            ]],
            'tookMs' => $tookMs,
        ];
    }
}
