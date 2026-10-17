<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * Renders a {@see MeterInterface} snapshot into the Prometheus text
 * exposition format (version 0.0.4) -- the metrics family's delivery
 * face for the scrape endpoints Prometheus, VictoriaMetrics and the
 * rest of the ecosystem poll.
 *
 * SEMANTICS (the reference's own, aligned with the code and not
 * ahead of it): a counter series (`increment`) renders its count
 * ONLY -- a pure counter has count == sum, so a `_sum` member would
 * duplicate the value and is not emitted; an observation series
 * (`observe`) whose sum has diverged from its count renders the
 * classic histogram members `_sum` and `_count` beside the bare
 * series, the divergence judgement carrying the NaN contract ({@see
 * PrometheusNumbers::diverged()}'s own record). The TYPE header
 * emits once per metric name, the bookkeeping the render's own
 * carried set answers.
 *
 * THE RENDER IS STATELESS AND BOUNDED: no I/O, no clock, no
 * configuration -- the surface that serves it lives in the calling
 * lane ({@see \Zef\Framework\Middleware\MetricsMiddleware}), and the
 * render stops at its own series ceiling ({@see self::MAX_SERIES})
 * so a pathological snapshot cannot turn one scrape into an
 * unbounded response. The series identity ({@see PrometheusSeries}),
 * the metric-name stem ({@see PrometheusMetricName}), the label
 * block ({@see PrometheusLabels}) and the number forms ({@see
 * PrometheusNumbers}) are the render's own units -- each alphabet
 * and judgement one named place the suite reads directly.
 *
 * Ported from the reference implementation (legacy
 * `zef-framework-src` `Infrastructure/Observability/
 * PrometheusRenderer.php`): the TYPE-once-per-metric bookkeeping,
 * the histogram-member rule with its NaN contract, the sanitisation
 * alphabets and prefixes, the label value escaping, the static-label
 * merge and the number forms all move verbatim, over the grammar and
 * judgement units the render composes.
 */
final class PrometheusRenderer
{
    /**
     * The render's own series ceiling: one scrape answers at most
     * this many series, the meter's own bound already keeping the
     * snapshot far under it.
     */
    private const int MAX_SERIES = 4096;

    /**
     * Renders the meter's accumulated series into the exposition
     * format, the static labels appended to every series.
     *
     * @param MeterInterface       $meter        the engine whose snapshot renders
     * @param array<string,string> $staticLabels the label pairs every series carries
     */
    public function render(MeterInterface $meter, array $staticLabels = []): string
    {
        $lines = [];
        $emittedTypes = [];
        $seriesCount = 0;

        foreach ($meter->snapshot() as $rawKey => $series) {
            ++$seriesCount;
            if ($seriesCount > self::MAX_SERIES) {
                break;
            }

            $identity = PrometheusSeries::identity($rawKey, $series);
            $labelBlock = PrometheusLabels::block($identity['attributes'], $staticLabels);
            $count = (float) $series['count'];
            $sum = $series['sum'];

            $this->appendSeriesLines($lines, $emittedTypes, $identity['metric'], $labelBlock, $count, $sum);
        }

        if ($lines === []) {
            return '';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Appends one series, and its classic histogram members when the
     * observations have diverged (count != sum), to the output lines
     * -- the TYPE header emitted once per metric name.
     *
     * @param list<string>       $lines        the output buffer, appended in place
     * @param array<string,true> $emittedTypes the TYPE headers already emitted
     */
    private function appendSeriesLines(
        array &$lines,
        array &$emittedTypes,
        string $metricName,
        string $labelBlock,
        float $count,
        float $sum,
    ): void {
        if (!isset($emittedTypes[$metricName])) {
            $emittedTypes[$metricName] = true;
            $lines[] = "# TYPE {$metricName} counter";
        }

        $lines[] = \sprintf('%s%s %s', $metricName, $labelBlock, PrometheusNumbers::rendered($count));

        PrometheusHistogram::appendMembers($lines, $emittedTypes, $metricName, $labelBlock, $count, $sum);
    }
}
