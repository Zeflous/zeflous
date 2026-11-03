<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\LogRecord;
use Zef\Framework\Telemetry\OtlpJsonPayloadEncoder;
use Zef\Framework\Telemetry\OtlpLogsPayload;

/**
 * The logs signal's payload fold, on its own terms: the exact wire
 * shape one record batch encodes to -- the resource block and the
 * scope the lane names, every record in the batch's own order
 * with its timestamp as the protocol's string nanoseconds, the
 * severity's spelling, the body as the string value it arrived
 * with, and the KeyValue list the dimensions ride.
 *
 * The body's verbatim posture is the pin the suite owes the
 * doctrine: the fold never clamps what it encodes, because the
 * recording point owns the hygiene -- a body longer than the
 * hygiene unit's own clamp rides whole, and the assertion that
 * says so is the guard a well-meaning clamp would have to kill.
 *
 * @internal
 */
#[CoversClass(OtlpLogsPayload::class)]
#[CoversClass(LogRecord::class)]
#[CoversClass(OtlpJsonPayloadEncoder::class)]
final class OtlpLogsPayloadTest extends TestCase
{
    public function testTheBatchEncodesToTheExactWireShape(): void
    {
        $payload = OtlpLogsPayload::payload(
            ['service.name' => 'zef-test-service'],
            [
                new LogRecord('ERROR', 'upstream 503', 5, ['http.method' => 'GET', 'attempts' => 2]),
                new LogRecord('INFO', 'ready', 6),
            ],
        );

        self::assertSame([
            'resourceLogs' => [
                [
                    'resource' => ['attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'zef-test-service']],
                    ]],
                    'scopeLogs' => [
                        [
                            'scope' => ['name' => 'zef-observability'],
                            'logRecords' => [
                                [
                                    'timeUnixNano' => '5',
                                    'severityText' => 'ERROR',
                                    'body' => ['stringValue' => 'upstream 503'],
                                    'attributes' => [
                                        ['key' => 'http.method', 'value' => ['stringValue' => 'GET']],
                                        ['key' => 'attempts', 'value' => ['intValue' => '2']],
                                    ],
                                ],
                                [
                                    'timeUnixNano' => '6',
                                    'severityText' => 'INFO',
                                    'body' => ['stringValue' => 'ready'],
                                    'attributes' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $payload);
    }

    public function testTheBodyRidesVerbatimPastTheHygieneUnitsOwnClamp(): void
    {
        $body = str_repeat('x', 2049);

        $payload = OtlpLogsPayload::payload([], [new LogRecord('INFO', $body, 1)]);

        self::assertSame([
            'resourceLogs' => [
                [
                    'resource' => ['attributes' => []],
                    'scopeLogs' => [
                        [
                            'scope' => ['name' => 'zef-observability'],
                            'logRecords' => [
                                [
                                    'timeUnixNano' => '1',
                                    'severityText' => 'INFO',
                                    'body' => ['stringValue' => $body],
                                    'attributes' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $payload, 'the fold encodes the record exactly as it arrived -- the recording point owns the hygiene');
    }

    public function testTheRecordsKeepTheBatchesOwnOrder(): void
    {
        // The bodies run against the alphabet on purpose: a fold
        // that reordered the batch would pass an alphabetical pin
        // and fail the cadence's own delivery order.
        $payload = OtlpLogsPayload::payload([], [
            new LogRecord('INFO', 'second-in-alphabet-first-in-batch', 1),
            new LogRecord('ERROR', 'first-in-alphabet-last-in-batch', 2),
        ]);

        self::assertSame([
            'resourceLogs' => [
                [
                    'resource' => ['attributes' => []],
                    'scopeLogs' => [
                        [
                            'scope' => ['name' => 'zef-observability'],
                            'logRecords' => [
                                [
                                    'timeUnixNano' => '1',
                                    'severityText' => 'INFO',
                                    'body' => ['stringValue' => 'second-in-alphabet-first-in-batch'],
                                    'attributes' => [],
                                ],
                                [
                                    'timeUnixNano' => '2',
                                    'severityText' => 'ERROR',
                                    'body' => ['stringValue' => 'first-in-alphabet-last-in-batch'],
                                    'attributes' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $payload);
    }
}
