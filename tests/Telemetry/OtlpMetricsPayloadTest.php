<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\OtlpJsonPayloadEncoder;
use Zef\Framework\Telemetry\OtlpMetricKey;
use Zef\Framework\Telemetry\OtlpMetricsPayload;

/**
 * The metrics signal's payload fold, on its own terms: the exact
 * wire shape one meter snapshot encodes to -- the resource block
 * and the scope the lane names, every series in the snapshot's
 * own order as the protocol's sum data point, the cumulative and
 * monotonic arms the counter family's posture spells, the delivery
 * stamp as the protocol's string nanoseconds, and the KeyValue
 * list the dimensions ride.
 *
 * The fold is the exporter's own shape unit: the assertions pin
 * the whole map exactly ({@see OtlpJsonPayloadEncoder}'s own suite
 * owns the anyValue typing arms this fold composes), so a shape
 * that drifts -- a field renamed, an arm inverted, a series
 * dropped, a stamp unspelled -- fails the pin, not a downstream
 * collector's patience. The real-meter lane pins the split the
 * hand-made maps could not see (the issue #779 finding): a
 * {@see CounterMeter} snapshot keyed by the composite identity
 * answers ONE metric per NAME with one data point per series,
 * the names the caller spelled, never the composite key whole.
 *
 * @internal
 */
#[CoversClass(OtlpJsonPayloadEncoder::class)]
#[CoversClass(OtlpMetricKey::class)]
#[CoversClass(OtlpMetricsPayload::class)]
final class OtlpMetricsPayloadTest extends TestCase
{
    public function testTheSnapshotEncodesToTheExactWireShape(): void
    {
        $payload = OtlpMetricsPayload::payload(
            ['service.name' => 'zef-test-service'],
            [
                'zef.http.errors.total' => [
                    'count' => 3,
                    'sum' => 3.0,
                    'attributes' => ['route' => '/api'],
                ],
                'zef.requests' => [
                    'count' => 2,
                    'sum' => 7.0,
                    'attributes' => [],
                ],
            ],
            1_700_000_000_000_000_000,
        );

        self::assertSame([
            'resourceMetrics' => [
                [
                    'resource' => ['attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'zef-test-service']],
                    ]],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'zef-observability'],
                            'metrics' => [
                                [
                                    'name' => 'zef.http.errors.total',
                                    'sum' => [
                                        'aggregationTemporality' => 2,
                                        'isMonotonic' => true,
                                        'dataPoints' => [
                                            [
                                                'asDouble' => 3.0,
                                                'timeUnixNano' => '1700000000000000000',
                                                'attributes' => [
                                                    ['key' => 'route', 'value' => ['stringValue' => '/api']],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'name' => 'zef.requests',
                                    'sum' => [
                                        'aggregationTemporality' => 2,
                                        'isMonotonic' => true,
                                        'dataPoints' => [
                                            [
                                                'asDouble' => 7.0,
                                                'timeUnixNano' => '1700000000000000000',
                                                'attributes' => [],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $payload);
    }

    public function testARealMeterSnapshotShipsOneMetricPerNameWithTheSeriesOwnDimensions(): void
    {
        // The issue #779 repro verbatim: the meter's snapshot is keyed
        // by the composite identity (`MeterSlot`'s `name . '|' . json`
        // grammar), and the fold must split it back before the wire
        // carries it. The hand-made maps above cannot see the
        // mismatch -- their keys carry no separator -- so this lane
        // feeds the meter's own answer, the exact shape the exporter
        // faces: `zef.http.errors.total` recorded under two dimension
        // sets answers ONE metric whose data points carry both series,
        // the names the caller spelled, never the `name|{...}`
        // composite whole.
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.http.errors.total', 2, ['http.request.method' => 'GET']);
        $counterMeter->increment('zef.http.errors.total', 1, ['http.request.method' => 'POST']);
        $counterMeter->increment('zef.requests', 1, ['route' => '/api']);

        $payload = OtlpMetricsPayload::payload(
            ['service.name' => 'zef-test-service'],
            $counterMeter->snapshot(),
            1_700_000_000_000_000_000,
        );

        self::assertSame([
            'resourceMetrics' => [
                [
                    'resource' => ['attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'zef-test-service']],
                    ]],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'zef-observability'],
                            'metrics' => [
                                [
                                    'name' => 'zef.http.errors.total',
                                    'sum' => [
                                        'aggregationTemporality' => 2,
                                        'isMonotonic' => true,
                                        'dataPoints' => [
                                            [
                                                'asDouble' => 2.0,
                                                'timeUnixNano' => '1700000000000000000',
                                                'attributes' => [
                                                    [
                                                        'key' => 'http.request.method',
                                                        'value' => ['stringValue' => 'GET'],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'asDouble' => 1.0,
                                                'timeUnixNano' => '1700000000000000000',
                                                'attributes' => [
                                                    [
                                                        'key' => 'http.request.method',
                                                        'value' => ['stringValue' => 'POST'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'name' => 'zef.requests',
                                    'sum' => [
                                        'aggregationTemporality' => 2,
                                        'isMonotonic' => true,
                                        'dataPoints' => [
                                            [
                                                'asDouble' => 1.0,
                                                'timeUnixNano' => '1700000000000000000',
                                                'attributes' => [
                                                    ['key' => 'route', 'value' => ['stringValue' => '/api']],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $payload);
    }

    public function testTheSeriesKeepTheSnapshotsOwnOrder(): void
    {
        // The names run against the alphabet on purpose: a fold
        // that sorted the series -- or keyed them anywhere but the
        // snapshot's own order -- would pass an alphabetical pin
        // and fail the wire the meter actually answered.
        $payload = OtlpMetricsPayload::payload(
            [],
            [
                'zef.second' => ['count' => 1, 'sum' => 1.0, 'attributes' => []],
                'zef.first-declared-last' => ['count' => 1, 'sum' => 1.0, 'attributes' => []],
            ],
            5,
        );

        self::assertSame([
            'resourceMetrics' => [
                [
                    'resource' => ['attributes' => []],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'zef-observability'],
                            'metrics' => [
                                [
                                    'name' => 'zef.second',
                                    'sum' => [
                                        'aggregationTemporality' => 2,
                                        'isMonotonic' => true,
                                        'dataPoints' => [
                                            [
                                                'asDouble' => 1.0,
                                                'timeUnixNano' => '5',
                                                'attributes' => [],
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'name' => 'zef.first-declared-last',
                                    'sum' => [
                                        'aggregationTemporality' => 2,
                                        'isMonotonic' => true,
                                        'dataPoints' => [
                                            [
                                                'asDouble' => 1.0,
                                                'timeUnixNano' => '5',
                                                'attributes' => [],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $payload);
    }
}
