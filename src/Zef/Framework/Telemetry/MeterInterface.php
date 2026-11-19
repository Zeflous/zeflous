<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The metrics lane's own contract: the bounded counter/observation
 * surface the campaign's metrics family lands, exactly where the
 * tracing core's own porting notes reserved the arrival -- the
 * engine half of a family whose config face stays in `Http` and
 * whose composition seam stays in `Middleware`, the same split every
 * campaign family carries.
 *
 * The contract is deliberately the reference's own three-method
 * shape: `increment()` counts events (the counter -- a monotonic
 * total), `observe()` records measurements against a name (the
 * histogram-style series -- a count plus a sum), and `snapshot()`
 * answers the accumulated series table for an exposition renderer
 * ({@see PrometheusRenderer}) or an exporter to read. The
 * {@see CounterMeter} implementation is the in-process engine; the
 * void path this contract does NOT spell is the absent family -- a
 * deployment that leaves the metrics family unarmed never constructs
 * a meter at all, which is the honest void (no allocation, no
 * accounting) rather than a noop object answering empty.
 *
 * Series identity is the pair (name, attributes): the same name
 * under different attribute sets is a different series, and the
 * implementation's own bounds ({@see CounterMeter}'s series ceiling
 * and dimension hygiene) keep the identity space finite -- the
 * contract itself carries no bound because the bound is an
 * implementation posture, not a counting rule.
 */
interface MeterInterface
{
    /**
     * Counts an event against a name: the counter's monotonic total.
     *
     * The monotonic posture is the contract's own: a negative
     * increment is refused ({@see CounterMeter} throws), because the
     * exposition families that render the total -- the OTLP payload's
     * `isMonotonic: true`, the Prometheus render's `# TYPE counter`
     * -- declare it monotonic and a rewound count is a lie both
     * readers pay for.
     *
     * @param string              $name       the metric name, the dotted
     *                                        namespace the framework's own
     *                                        series carry
     *                                        (`zef.http.errors.total`)
     * @param float|int           $value      the increment, one by default;
     *                                        never negative
     * @param array<string,mixed> $attributes the series' dimensions
     *
     * @throws InvalidArgumentException when the increment is negative
     */
    public function increment(string $name, float|int $value = 1, array $attributes = []): void;

    /**
     * Records a measurement against a name: the observation series'
     * count grows by one and its sum by the value.
     *
     * @param string              $name       the metric name
     * @param float               $value      the measured value
     * @param array<string,mixed> $attributes the series' dimensions
     */
    public function observe(string $name, float $value, array $attributes = []): void;

    /**
     * Answers the accumulated series table, keyed by the composite
     * series identity the implementation mints (the name and the
     * normalised attributes, rendered as the implementation's own
     * key grammar spells them).
     *
     * @return array<string,array{count:float|int,sum:float,attributes:array<string,mixed>}>
     */
    public function snapshot(): array;
}
