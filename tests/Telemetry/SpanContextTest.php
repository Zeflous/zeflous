<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\SpanContext;

/**
 * The W3C trace-context identity, on its own terms: the grammar's
 * acceptance and refusal, the `traceparent` render's exact shape,
 * the void singleton's honest answers, and the trace-state companion
 * riding along untouched for the propagation lane that owns its
 * grammar.
 *
 * @internal
 */
#[CoversClass(SpanContext::class)]
final class SpanContextTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string SPAN_ID = '00f067aa0ba902b7';

    public function testAValidContextRendersTheW3CTraceParent(): void
    {
        $spanContext = new SpanContext(self::TRACE_ID, self::SPAN_ID, true);

        self::assertSame('00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01', $spanContext->traceParent());
        self::assertTrue($spanContext->isValid());
        self::assertTrue($spanContext->sampled);
        self::assertNull($spanContext->traceState);
    }

    public function testAnUnsampledContextRendersTheUnsampledFlag(): void
    {
        $spanContext = new SpanContext(self::TRACE_ID, self::SPAN_ID, false);

        self::assertSame('00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-00', $spanContext->traceParent());
        self::assertFalse($spanContext->sampled);
    }

    public function testTheDefaultSamplingFlagIsSampled(): void
    {
        $spanContext = new SpanContext(self::TRACE_ID, self::SPAN_ID);

        self::assertTrue($spanContext->sampled, 'an unspecified sampling flag reads as sampled');
        self::assertSame('00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01', $spanContext->traceParent());
    }

    public function testTheTraceStateCompanionRidesAlongUntouched(): void
    {
        $spanContext = new SpanContext(self::TRACE_ID, self::SPAN_ID, true, 'vendor=1,other=2');

        self::assertSame('vendor=1,other=2', $spanContext->traceState);
        self::assertSame('00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01', $spanContext->traceParent());
    }

    public function testTheVoidSingletonIsGrammaticalButVoid(): void
    {
        $context = SpanContext::invalid();

        self::assertSame(str_repeat('0', 32), $context->traceId);
        self::assertSame(str_repeat('0', 16), $context->spanId);
        self::assertFalse($context->sampled);
        self::assertFalse($context->isValid());
        self::assertSame('00-' . str_repeat('0', 32) . '-' . str_repeat('0', 16) . '-00', $context->traceParent());
    }

    public function testAnAllZeroTraceIdIsValidGrammarButNotAValidContext(): void
    {
        $spanContext = new SpanContext(str_repeat('0', 32), self::SPAN_ID);

        self::assertFalse($spanContext->isValid());
    }

    public function testAnAllZeroSpanIdIsValidGrammarButNotAValidContext(): void
    {
        $spanContext = new SpanContext(self::TRACE_ID, str_repeat('0', 16));

        self::assertFalse($spanContext->isValid());
    }

    #[DataProvider('provideAMalformedTraceIdIsRefusedAtConstructionCases')]
    public function testAMalformedTraceIdIsRefusedAtConstruction(string $traceId): void
    {
        try {
            new SpanContext($traceId, self::SPAN_ID);
            self::fail('A malformed trace id must be refused at construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('Invalid W3C trace context identifiers.', $invalidArgumentException->getMessage());
        }
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideAMalformedTraceIdIsRefusedAtConstructionCases(): iterable
    {
        yield 'too short' => ['4bf92f35'];

        yield 'too long' => [self::TRACE_ID . 'ab'];

        yield 'uppercase hex' => ['4BF92F3577B34DA6A3CE929D0E0E4736'];

        yield 'non-hex characters' => ['4bf92f3577b34da6a3ce929d0e0e47zz'];
    }

    #[DataProvider('provideAMalformedSpanIdIsRefusedAtConstructionCases')]
    public function testAMalformedSpanIdIsRefusedAtConstruction(string $spanId): void
    {
        try {
            new SpanContext(self::TRACE_ID, $spanId);
            self::fail('A malformed span id must be refused at construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('Invalid W3C trace context identifiers.', $invalidArgumentException->getMessage());
        }
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideAMalformedSpanIdIsRefusedAtConstructionCases(): iterable
    {
        yield 'too short' => ['00f067aa'];

        yield 'too long' => [self::SPAN_ID . 'ab'];

        yield 'uppercase hex' => ['00F067AA0BA902B7'];

        yield 'non-hex characters' => ['00f067aa0ba902b!'];
    }
}
