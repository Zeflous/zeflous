<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CorrelationIdentity;

/**
 * The correlation context's W3C identity, on its own terms: the four
 * grammars' acceptance and refusal, the `traceparent` render, the
 * separator-inclusive wire accounting, and the hard wire ceiling --
 * the value object the operation-level propagation carries as its
 * first half.
 *
 * @internal
 */
#[CoversClass(CorrelationIdentity::class)]
final class CorrelationIdentityTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string SPAN_ID = '00f067aa0ba902b7';

    public function testAValidIdentityRendersTheW3CTraceParent(): void
    {
        $correlationIdentity = new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', null);

        self::assertSame('00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-01', $correlationIdentity->traceParent());
        self::assertSame(55, $correlationIdentity->propagationBytes());
    }

    public function testTheUnsampledFlagsRender(): void
    {
        $correlationIdentity = new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '00', null);

        self::assertSame('00-' . self::TRACE_ID . '-' . self::SPAN_ID . '-00', $correlationIdentity->traceParent());
    }

    public function testTheTraceStateRidesAlongAndCountsItsSeparator(): void
    {
        $traceState = 'vendor1=alpha,vendor2=beta,rojo=00f067aa0ba902b7';

        $correlationIdentity = new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', $traceState);

        self::assertSame($traceState, $correlationIdentity->traceState);
        self::assertSame(55 + 1 + mb_strlen($traceState), $correlationIdentity->propagationBytes());
    }

    public function testTheTraceStateAtItsCeilingShips(): void
    {
        $atCeiling = 'k=' . str_repeat('a', CorrelationIdentity::MAX_TRACESTATE_BYTES - 2);

        $correlationIdentity = new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', $atCeiling);

        self::assertSame($atCeiling, $correlationIdentity->traceState);
        self::assertSame(
            CorrelationIdentity::MAX_TRACEPARENT_BYTES + 1 + CorrelationIdentity::MAX_TRACESTATE_BYTES,
            $correlationIdentity->propagationBytes(),
        );
    }

    public function testRejectsTheGrammarValidTraceStateThatExceedsItsCeiling(): void
    {
        $this->assertRejected(
            static fn (): CorrelationIdentity => new CorrelationIdentity(
                self::TRACE_ID,
                self::SPAN_ID,
                '01',
                'k=' . str_repeat('a', CorrelationIdentity::MAX_TRACESTATE_BYTES - 1),
            ),
            'the tracestate ceiling',
        );
    }

    #[DataProvider('provideRejectsTheTraceIdThatFailsTheGrammarCases')]
    public function testRejectsTheTraceIdThatFailsTheGrammar(string $traceId): void
    {
        $this->assertRejected(
            static fn (): CorrelationIdentity => new CorrelationIdentity($traceId, self::SPAN_ID, '01', null),
            'the trace id grammar',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRejectsTheTraceIdThatFailsTheGrammarCases(): iterable
    {
        yield 'non-hex' => [str_repeat('g', 32)];

        yield 'all zero' => [str_repeat('0', 32)];

        yield 'short' => [mb_substr(self::TRACE_ID, 0, 31)];

        yield 'uppercase' => [mb_strtoupper(self::TRACE_ID)];

        yield 'a hex tail beyond the width' => ['g' . self::TRACE_ID];

        yield 'a hex head beyond the width' => [self::TRACE_ID . 'g'];
    }

    #[DataProvider('provideRejectsTheSpanIdThatFailsTheGrammarCases')]
    public function testRejectsTheSpanIdThatFailsTheGrammar(string $spanId): void
    {
        $this->assertRejected(
            static fn (): CorrelationIdentity => new CorrelationIdentity(self::TRACE_ID, $spanId, '01', null),
            'the span id grammar',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRejectsTheSpanIdThatFailsTheGrammarCases(): iterable
    {
        yield 'non-hex' => [str_repeat('x', 16)];

        yield 'all zero' => [str_repeat('0', 16)];

        yield 'short' => [mb_substr(self::SPAN_ID, 0, 15)];

        yield 'a hex tail beyond the width' => ['g' . self::SPAN_ID];

        yield 'a hex head beyond the width' => [self::SPAN_ID . 'g'];
    }

    #[DataProvider('provideRejectsTheFlagsThatCarryMoreThanTheSampledBitCases')]
    public function testRejectsTheFlagsThatCarryMoreThanTheSampledBit(string $traceFlags): void
    {
        $this->assertRejected(
            static fn (): CorrelationIdentity => new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, $traceFlags, null),
            'the trace flags grammar',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRejectsTheFlagsThatCarryMoreThanTheSampledBitCases(): iterable
    {
        yield 'sampled bit plus another' => ['03'];

        yield 'a bit beyond the sampled bit alone' => ['02'];

        yield 'every bit set' => ['ff'];

        yield 'a single digit' => ['1'];

        yield 'non-hex' => ['0g'];

        yield 'a four-digit sampled-only fold' => ['0101'];
    }

    #[DataProvider('provideRejectsTheTraceStateThatFailsTheMemberGrammarCases')]
    public function testRejectsTheTraceStateThatFailsTheMemberGrammar(?string $traceState): void
    {
        $this->assertRejected(
            static fn (): CorrelationIdentity => new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', $traceState),
            'the tracestate member grammar',
        );
    }

    /**
     * @return iterable<string, array{null|string}>
     */
    public static function provideRejectsTheTraceStateThatFailsTheMemberGrammarCases(): iterable
    {
        yield 'empty' => [''];

        yield 'oversized' => [str_repeat('x', CorrelationIdentity::MAX_TRACESTATE_BYTES + 1)];

        yield 'uppercase key' => ['UPPER=value'];

        yield 'missing value' => ['=novalue'];

        yield 'key with a space' => ['bad key=value'];
    }

    public function testAMultiVendorTraceStateIsOneMemberList(): void
    {
        $correlationIdentity = new CorrelationIdentity(
            self::TRACE_ID,
            self::SPAN_ID,
            '01',
            'vendor1=alpha,vendor2=beta,rojo=00f067aa0ba902b7',
        );

        self::assertNotNull($correlationIdentity->traceState);
    }

    public function testRejectsTheTraceStateThatSailsPastEveryBound(): void
    {
        // The hard wire-size ceiling (8192) is unreachable by
        // construction -- the tracestate's own 512-byte bound caps the
        // pair at 568 -- so a companion this oversized meets the
        // tracestate bound first, whichever bound the reader expects.
        $this->assertRejected(
            static fn (): CorrelationIdentity => new CorrelationIdentity(
                self::TRACE_ID,
                self::SPAN_ID,
                '01',
                str_repeat('k=', 100) . str_repeat('v', CorrelationIdentity::MAX_PROPAGATION_BYTES),
            ),
            'the first bound the oversized companion meets',
        );
    }

    /**
     * @param callable(): CorrelationIdentity $construction
     */
    private function assertRejected(callable $construction, string $because): void
    {
        try {
            $construction();
            self::fail("An identity that fails {$because} must be rejected.");
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }
}
