<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The exposition's histogram members: the classic `_sum` and
 * `_count` lines an observation series earns when its observations
 * have diverged from its count -- the unit the {@see
 * PrometheusRenderer} appends per series, extracted so the counter
 * line and the members' own condition stay one named place each.
 *
 * The members ride only on divergence ({@see
 * PrometheusNumbers::diverged()}, the NaN contract its own record):
 * a pure counter keeps count == sum, so the members would duplicate
 * the value the counter line already answered. The TYPE headers for
 * the members emit once per metric name, over the bookkeeping set
 * the render carries.
 */
final class PrometheusHistogram
{
    /**
     * Appends the histogram members for one series, when the series
     * has earned them, to the output lines -- the TYPE headers
     * emitted once per metric name.
     *
     * @param list<string>       $lines        the output buffer, appended in place
     * @param array<string,true> $emittedTypes the TYPE headers already emitted
     */
    public static function appendMembers(
        array &$lines,
        array &$emittedTypes,
        string $metricName,
        string $labelBlock,
        float $count,
        float $sum,
    ): void {
        if (!PrometheusNumbers::diverged($count, $sum)) {
            return;
        }

        if (!isset($emittedTypes[$metricName . '_sum'])) {
            $emittedTypes[$metricName . '_sum'] = true;
            $lines[] = "# TYPE {$metricName}_sum counter";
            $lines[] = "# TYPE {$metricName}_count counter";
        }

        $lines[] = \sprintf('%s_sum%s %s', $metricName, $labelBlock, PrometheusNumbers::rendered($sum));
        $lines[] = \sprintf('%s_count%s %s', $metricName, $labelBlock, PrometheusNumbers::rendered($count));
    }
}
