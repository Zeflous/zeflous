<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\BatchSpanProcessor;
use Zef\Framework\Telemetry\InMemorySpanExporter;
use Zef\Framework\Telemetry\NoopSpan;
use Zef\Framework\Telemetry\Span;
use Zef\Framework\Telemetry\SpanContext;
use Zef\Framework\Telemetry\Tracer;
use Zef\Test\Support\Telemetry\SteppingTelemetryClock;

/**
 * The live tracer, on its own terms: the identity minting (a fresh
 * trace for a root span, the parent's trace inherited for a child),
 * the wiring (the span's end hands its record to the processor the
 * tracer built it over), the clock the tracer threads into every
 * span, and the arm -- a disabled tracer answers the void span
 * without reading the clock at all.
 *
 * @internal
 */
#[CoversClass(Tracer::class)]
final class TracerTest extends TestCase
{
    private const string PARENT_TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string PARENT_SPAN_ID = '00f067aa0ba902b7';

    private InMemorySpanExporter $inMemorySpanExporter;

    private BatchSpanProcessor $batchSpanProcessor;

    private SteppingTelemetryClock $steppingTelemetryClock;

    #[Override]
    protected function setUp(): void
    {
        $this->inMemorySpanExporter = new InMemorySpanExporter();
        $this->batchSpanProcessor = new BatchSpanProcessor($this->inMemorySpanExporter);
        $this->steppingTelemetryClock = new SteppingTelemetryClock(
            monotonicEpoch: 1_000,
            monotonicStep: 5_000,
            wallEpoch: 1_000_000_000,
            wallStep: 1_000,
        );
    }

    public function testARootSpanMintsAFreshW3CIdentity(): void
    {
        $span = $this->tracer()->startSpan('request');

        self::assertInstanceOf(Span::class, $span);
        $context = $span->getContext();
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $context->traceId);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/D', $context->spanId);
        self::assertTrue($context->sampled, 'a minted span is always sampled');
        self::assertTrue($context->isValid());
        self::assertNotSame(self::PARENT_TRACE_ID, $context->traceId);
    }

    public function testTwoRootSpansMintDistinctIdentities(): void
    {
        $tracer = $this->tracer();

        $first = $tracer->startSpan('first')->getContext();
        $second = $tracer->startSpan('second')->getContext();

        self::assertNotSame($first->traceId, $second->traceId);
        self::assertNotSame($first->spanId, $second->spanId);
    }

    public function testAChildSpanInheritsTheParentsTraceId(): void
    {
        $spanContext = new SpanContext(self::PARENT_TRACE_ID, self::PARENT_SPAN_ID);
        $child = $this->tracer()->startSpan('child', [], $spanContext);

        self::assertSame(self::PARENT_TRACE_ID, $child->getContext()->traceId);
        self::assertNotSame(self::PARENT_SPAN_ID, $child->getContext()->spanId, 'the child mints its own span id');

        $child->end();
        $this->batchSpanProcessor->flush();

        self::assertSame(
            $spanContext,
            $this->inMemorySpanExporter->spans()[0]->parent,
            'the parent context rides whole in the record',
        );
    }

    public function testTheSpanThreadsTheTracersClock(): void
    {
        $span = $this->tracer()->startSpan('request', ['http.method' => 'GET']);
        $span->end();

        $this->batchSpanProcessor->flush();

        $record = $this->inMemorySpanExporter->spans()[0];
        self::assertSame(1_000, $record->startNs);
        self::assertSame(1_000_000_000, $record->startUnixNano);
        self::assertSame(6_000, $record->endNs);
        self::assertSame(['http.method' => 'GET'], $record->attributes);
    }

    public function testTheEndHandsTheRecordToTheProcessorsQueue(): void
    {
        $tracer = $this->tracer();

        $first = $tracer->startSpan('first');
        $second = $tracer->startSpan('second', [], $first->getContext());
        $first->end();
        $second->end();
        $this->batchSpanProcessor->flush();

        self::assertCount(2, $this->inMemorySpanExporter->spans());
        self::assertSame('first', $this->inMemorySpanExporter->spans()[0]->name);
        self::assertSame('second', $this->inMemorySpanExporter->spans()[1]->name);
    }

    public function testADisarmedTracerAnswersTheVoidSpanAndNeverReadsTheClock(): void
    {
        $tracer = new Tracer($this->batchSpanProcessor, $this->steppingTelemetryClock, false);

        $span = $tracer->startSpan('request');

        self::assertInstanceOf(NoopSpan::class, $span);
        self::assertTrue($span->isEnded());
        self::assertFalse($span->getContext()->isValid());
        self::assertSame(0, $this->steppingTelemetryClock->monotonicReadCount(), 'a disarmed family never measures');
        self::assertSame(0, $this->steppingTelemetryClock->wallReadCount(), 'a disarmed family never opens a timeline');
    }

    public function testTheDefaultArmIsEnabled(): void
    {
        $tracer = new Tracer($this->batchSpanProcessor, $this->steppingTelemetryClock);

        $span = $tracer->startSpan('request');

        self::assertInstanceOf(Span::class, $span, 'an unspecified arm reads as enabled');
    }

    /**
     * One armed tracer over the test's frozen clock.
     */
    private function tracer(): Tracer
    {
        return new Tracer($this->batchSpanProcessor, $this->steppingTelemetryClock, true);
    }
}
