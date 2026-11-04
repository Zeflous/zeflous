<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\OtlpJsonPayloadEncoder;
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
 * collector's patience.
 *
 * @internal
 */
#[CoversClass(OtlpMetricsPayload::class)]
#[CoversClass(OtlpJsonPayloadEncoder::class)]
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
