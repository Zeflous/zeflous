<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\SpanContext;
use Zef\Framework\Telemetry\TraceContextPropagator;

/**
 * The W3C span-identity propagation pair, on its own terms: the
 * tolerant extraction's every refusal, the normalisation the record
 * keeps, and the outbound render -- the pair the correlation
 * boundary's request span is parented through.
 *
 * @internal
 */
#[CoversClass(TraceContextPropagator::class)]
final class TraceContextPropagatorTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string SPAN_ID = '00f067aa0ba902b7';

    public function testExtractsAWellFormedTraceParent(): void
    {
        $spanContext = TraceContextPropagator::extract(
            '00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01',
        );

        self::assertInstanceOf(SpanContext::class, $spanContext);
        self::assertSame(self::TRACE_ID, $spanContext->traceId);
        self::assertSame(self::SPAN_ID, $spanContext->spanId);
        self::assertTrue($spanContext->sampled);
        self::assertNull($spanContext->traceState);
    }

    public function testExtractsTheUnsampledFlagAsNotSampled(): void
    {
        $spanContext = TraceContextPropagator::extract(
            '00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-00',
        );

        self::assertInstanceOf(SpanContext::class, $spanContext);
        self::assertFalse($spanContext->sampled, 'the sampled flag is the one bit the grammar admits');
    }

    public function testExtractsTheTraceStateCompanionUntouched(): void
    {
        $spanContext = TraceContextPropagator::extract(
            '00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01',
            'vendor=alpha,other=beta',
        );

        self::assertInstanceOf(SpanContext::class, $spanContext);
        self::assertSame('vendor=alpha,other=beta', $spanContext->traceState);
    }

    public function testNormalisesUppercaseHexToLowercase(): void
    {
        $spanContext = TraceContextPropagator::extract(
            '00-' . mb_strtoupper(self::TRACE_ID) . '-' . mb_strtoupper(self::SPAN_ID) . '-01',
        );

        self::assertInstanceOf(SpanContext::class, $spanContext);
        self::assertSame(self::TRACE_ID, $spanContext->traceId);
        self::assertSame(self::SPAN_ID, $spanContext->spanId);
    }

    public function testTrimsSurroundingWhitespaceBeforeTheGrammar(): void
    {
        $spanContext = TraceContextPropagator::extract(
            ' 00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01 ',
        );

        self::assertInstanceOf(SpanContext::class, $spanContext);
        self::assertSame(self::TRACE_ID, $spanContext->traceId);
    }

    public function testRejectsTheAllZeroIdentity(): void
    {
        self::assertNull(TraceContextPropagator::extract(
            '00-' . str_repeat('0', 32) . '-' . self::SPAN_ID . '-01',
        ));
        self::assertNull(TraceContextPropagator::extract(
            '00-' . self::TRACE_ID . '-' . str_repeat('0', 16) . '-01',
        ));
    }

    public function testRejectsTheEmptyLineAsTheAbsentAnswer(): void
    {
        self::assertNull(TraceContextPropagator::extract(''));
    }

    public function testRejectsEveryMalformedLine(): void
    {
        self::assertNull(TraceContextPropagator::extract('invalid'));
        self::assertNull(TraceContextPropagator::extract(
            '01-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01',
        ), 'a future version is not this grammar');
        self::assertNull(TraceContextPropagator::extract(
            '00-' . mb_substr(self::TRACE_ID, 0, 31) . '-' . self::SPAN_ID . '-01',
        ), 'a short trace id is not this grammar');
        self::assertNull(TraceContextPropagator::extract(
            '00-' . self::TRACE_ID . '-' . mb_substr(self::SPAN_ID, 0, 15) . '-01',
        ), 'a short span id is not this grammar');
        self::assertNull(TraceContextPropagator::extract(
            '00-' . str_repeat('g', 32) . '-' . self::SPAN_ID . '-01',
        ), 'a non-hex trace id is not this grammar');
        self::assertNull(TraceContextPropagator::extract(
            '00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-1',
        ), 'a one-digit flag is not this grammar');
        self::assertNull(TraceContextPropagator::extract(
            '00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01-extra',
        ), 'an over-long line is not this grammar');
        self::assertNull(TraceContextPropagator::extract(
            'x00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01',
        ), 'a leading artefact is not this grammar');
    }

    public function testRejectsFlagBitsBeyondTheSampledBit(): void
    {
        self::assertNull(TraceContextPropagator::extract(
            '00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-02',
        ));
        self::assertNull(TraceContextPropagator::extract(
            '00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-ff',
        ));
    }

    public function testInjectsTheContextsOwnRender(): void
    {
        $spanContext = new SpanContext(self::TRACE_ID, self::SPAN_ID, true);

        self::assertSame(
            '00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01',
            TraceContextPropagator::inject($spanContext),
        );
    }

    public function testTheRoundTripCarriesTheIdentity(): void
    {
        $spanContext = new SpanContext(self::TRACE_ID, self::SPAN_ID, false, 'vendor=1');

        $extracted = TraceContextPropagator::extract(TraceContextPropagator::inject($spanContext), 'vendor=1');

        self::assertInstanceOf(SpanContext::class, $extracted);
        self::assertSame($spanContext->traceId, $extracted->traceId);
        self::assertSame($spanContext->spanId, $extracted->spanId);
        self::assertSame($spanContext->sampled, $extracted->sampled);
        self::assertSame($spanContext->traceState, $extracted->traceState);
    }
}
