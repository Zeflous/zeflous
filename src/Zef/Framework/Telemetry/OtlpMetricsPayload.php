<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The OTLP/JSON metrics payload fold: the `/v1/metrics` wire body
 * the exporter's delivery contract serialises -- the resource block,
 * the single scope the lane names, and the meter's accumulated
 * series as the protocol's sum data points. One shape, one unit,
 * so the exporter stays inside its own class-size budget and the
 * encoding logic sits in a fold of its own -- the same split the
 * archaeology gate demanded when the span shapes moved out of the
 * exporter ({@see OtlpJsonPayloadEncoder}, whose KeyValue arm this
 * fold composes for the resource and the series dimensions).
 *
 * The sum data point is the counter family's own posture: the
 * aggregation is cumulative (temporality 2 -- every snapshot
 * carries the series' whole total, never a delta), the value is
 * monotonic ({@see CounterMeter}'s series only ever grow, the
 * reference's own comment), and the tally the meter keeps beside
 * the sum does not ship at all -- the OTLP counter IS the total,
 * and the count is the meter's own bookkeeping. One data point per
 * series, stamped with the delivery's own wall reading -- the
 * caller's reading, a parameter the fold never mints on its own
 * behalf, so the injectable clock seam stays the exporter's to
 * wire and the suite's to freeze.
 *
 * Ported from the reference implementation (legacy
 * `zef-framework-src` `Infrastructure/Observability/
 * OtlpHttpJsonExporter.php`, the exportMetrics body): the payload
 * envelope, the sum block with its temporality and monotonic arms,
 * and the data point's double spelling move verbatim -- the fold's
 * own deviation is the shape's home: the reference built the list
 * inline in the exporter, and this port carries it as the unit the
 * ceiling demands. The reference's narrowing cast on the sum
 * (`(float)` before the meter's own float) is dropped as the dead
 * guard it was under the shape's contract -- the meter's snapshot
 * answers a float sum by construction (`CounterMeter`'s accumulate
 * advances it by a float), and the fold trusts the contract every
 * other internal seam trusts, the span lane's own dropped-guard
 * precedent.
 */
final class OtlpMetricsPayload
{
    /**
     * Encodes one delivery's whole payload: the resource block the
     * exporter's identity declared, the single scope the lane names,
     * and the meter's series as the protocol's sum data points -- the
     * stamp every data point carries is the delivery's own wall
     * reading, the caller's reading the fold never mints.
     *
     * @param array<string, mixed>                                                          $resource
     * @param array<string,array{count:float|int,sum:float,attributes:array<string,mixed>}> $metrics
     *
     * @return array<string, mixed>
     */
    public static function payload(array $resource, array $metrics, int $timeUnixNano): array
    {
        return [
            'resourceMetrics' => [
                [
                    'resource' => ['attributes' => OtlpJsonPayloadEncoder::attributes($resource)],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'zef-observability'],
                            'metrics' => self::metrics($metrics, $timeUnixNano),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Encodes the snapshot's series in the map's own order: one
     * protocol metric per series identity, the sum block carrying
     * the cumulative, monotonic data point.
     *
     * @param array<string,array{count:float|int,sum:float,attributes:array<string,mixed>}> $metrics
     *
     * @return list<array<string, mixed>>
     */
    private static function metrics(array $metrics, int $timeUnixNano): array
    {
        $encoded = [];

        foreach ($metrics as $name => $metric) {
            $encoded[] = [
                'name' => $name,
                'sum' => [
                    'aggregationTemporality' => 2,
                    'isMonotonic' => true,
                    'dataPoints' => [
                        [
                            'asDouble' => $metric['sum'],
                            'timeUnixNano' => (string) $timeUnixNano,
                            'attributes' => OtlpJsonPayloadEncoder::attributes($metric['attributes']),
                        ],
                    ],
                ],
            ];
        }

        return $encoded;
    }
}
