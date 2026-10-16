<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\SpanContext;
use Zef\Framework\Telemetry\SpanData;

/**
 * The frozen record, on its own terms: the fields ride whole, and
 * the duration render carries its own clamp -- a negative monotonic
 * delta is a clock artifact, never a negative duration, exactly as
 * the span's own end-time clamp holds upstream.
 *
 * @internal
 */
#[CoversClass(SpanData::class)]
final class SpanDataTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string SPAN_ID = '00f067aa0ba902b7';

    public function testTheRecordCarriesItsFieldsWhole(): void
    {
        $context = new SpanContext(self::TRACE_ID, self::SPAN_ID);
        $parent = new SpanContext(self::TRACE_ID, '1111111111111111');

        $spanData = new SpanData(
            'request',
            $context,
            $parent,
            1_000,
            5_500,
            1_000_000_000,
            1_000_004_500,
            'ERROR',
            'upstream 503',
            ['http.method' => 'GET'],
            [['name' => 'cache.miss', 'time_unix_nano' => 1_000_001_000, 'attributes' => ['key' => 'p:2']]],
        );

        self::assertSame('request', $spanData->name);
        self::assertSame($context, $spanData->context);
        self::assertSame($parent, $spanData->parent);
        self::assertSame(1_000, $spanData->startNs);
        self::assertSame(5_500, $spanData->endNs);
        self::assertSame(1_000_000_000, $spanData->startUnixNano);
        self::assertSame(1_000_004_500, $spanData->endUnixNano);
        self::assertSame('ERROR', $spanData->status);
        self::assertSame('upstream 503', $spanData->statusDescription);
        self::assertSame(['http.method' => 'GET'], $spanData->attributes);
        self::assertSame('cache.miss', $spanData->events[0]['name']);
        self::assertSame(4.5e-6, $spanData->durationSeconds());
    }

    public function testTheDurationRenderCarriesItsOwnClamp(): void
    {
        $spanContext = new SpanContext(self::TRACE_ID, self::SPAN_ID);

        $spanData = new SpanData(
            'broken-clock',
            $spanContext,
            null,
            5_000,
            1_000,
            1_000_000_000,
            999_999_996_000,
            'UNSET',
            null,
            [],
            [],
        );

        self::assertSame(0.0, $spanData->durationSeconds());
    }

    public function testAZeroSpanRendersZeroSeconds(): void
    {
        $spanContext = new SpanContext(self::TRACE_ID, self::SPAN_ID);

        $spanData = new SpanData(
            'instant',
            $spanContext,
            null,
            2_000,
            2_000,
            1_000_000_000,
            1_000_000_000,
            'UNSET',
            null,
            [],
            [],
        );

        self::assertSame(0.0, $spanData->durationSeconds());
    }
}
