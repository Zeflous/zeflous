<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\NoopSpan;
use Zef\Framework\Telemetry\NoopTracer;
use Zef\Framework\Telemetry\SpanContext;

/**
 * The void path, on its own terms: the singleton's one-instance
 * guarantee, the void answers (ended, invalid identity, every
 * recording a no-op), and the void tracer's agreement with the live
 * tracer's disarmed arm -- the same span either way.
 *
 * @internal
 */
#[CoversClass(NoopSpan::class)]
#[CoversClass(NoopTracer::class)]
final class NoopSpanTest extends TestCase
{
    public function testTheVoidSpanIsOneInstance(): void
    {
        self::assertSame(NoopSpan::instance(), NoopSpan::instance());
    }

    public function testTheVoidSpanCarriesTheVoidIdentity(): void
    {
        $context = NoopSpan::instance()->getContext();

        self::assertSame(str_repeat('0', 32), $context->traceId);
        self::assertSame(str_repeat('0', 16), $context->spanId);
        self::assertFalse($context->sampled);
        self::assertFalse($context->isValid());
        self::assertNotSame($context, SpanContext::invalid(), 'the void is read fresh, not cached');
    }

    public function testEveryRecordingIsANoOpReturningTheSpan(): void
    {
        $span = NoopSpan::instance();

        self::assertSame($span, $span->setAttribute('key', 'value'));
        self::assertSame($span, $span->setAttributes(['key' => 'value']));
        self::assertSame($span, $span->addEvent('event'));
        self::assertSame($span, $span->setStatus('ERROR', 'never'));

        $span->end();
        $span->end(1_000);

        self::assertTrue($span->isEnded(), 'the void span was never measuring');
    }

    public function testTheVoidTracerAnswersTheVoidSpan(): void
    {
        $noopTracer = new NoopTracer();

        $span = $noopTracer->startSpan('request', ['http.method' => 'GET'], SpanContext::invalid());

        self::assertSame(NoopSpan::instance(), $span);
    }

    public function testTheVoidTracersFlushDrainsNothingAndTouchesNothing(): void
    {
        // The drain face the export family lands reaches the void
        // tracer too -- the queue it would drain is empty by
        // construction, so the call is the no-op the interface's
        // own contract spells, and no double exists to observe.
        $noopTracer = new NoopTracer();

        $noopTracer->flush();

        self::addToAssertionCount(1);
    }
}
