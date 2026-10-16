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
        // Two members, not one: the spec's own value bound (256
        // characters per member) caps a single member well under the
        // companion's 512-byte ceiling, so the ceiling is reached the
        // way the wire actually reaches it -- a list.
        $atCeiling = 'k=' . str_repeat('a', 254) . ',k=' . str_repeat('a', 253);

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
                'k=' . str_repeat('a', 254) . ',k=' . str_repeat('a', 254),
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
            static fn (): CorrelationIdentity => new CorrelationIdentity(
                self::TRACE_ID,
                self::SPAN_ID,
                $traceFlags,
                null,
            ),
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
            static fn (): CorrelationIdentity => new CorrelationIdentity(
                self::TRACE_ID,
                self::SPAN_ID,
                '01',
                $traceState,
            ),
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

        yield 'digit-headed simple key' => ['5foo=bar'];

        yield 'empty value' => ['k='];

        yield 'equals inside the value' => ['a=b=c'];

        yield 'trailing newline past the anchor' => ["a=b\n"];

        yield 'trailing space inside the value' => ['rojo=trailing '];

        yield 'value beyond its own bound' => ['a=' . str_repeat('v', 257)];

        yield 'one member beyond the list bound' => [str_repeat('k=v,', 33) . 'k=v'];

        yield 'empty member between the commas' => ['a=1,,b=2'];

        yield 'whitespace member between the commas' => ['a=1, ,b=2'];

        yield 'a system id without its tenant' => ['@sys=v'];

        yield 'a tenant id without its system' => ['tenant@=v'];
    }

    #[DataProvider('provideAcceptsTheTraceStateTheW3CGrammarSpellsCases')]
    public function testAcceptsTheTraceStateTheW3CGrammarSpells(string $traceState): void
    {
        // The audit's own finding: the reference's approximation
        // refused W3C-valid lines outright, and the refusal the
        // propagation boundary folds to null is a silently dropped
        // correlation -- the multi-tenant keys and the value
        // characters the spec's ABNF admits are the finding's own
        // probes.
        $correlationIdentity = new CorrelationIdentity(self::TRACE_ID, self::SPAN_ID, '01', $traceState);

        self::assertSame($traceState, $correlationIdentity->traceState);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAcceptsTheTraceStateTheW3CGrammarSpellsCases(): iterable
    {
        yield 'the multi-tenant key' => ['tenant@system=v'];

        yield 'the multi-tenant key, digit-headed tenant' => ['5tenant@sys=v'];

        yield 'the multi-tenant key beside a simple key' => ['abc@x=1,def=2'];

        yield 'the greater-than character' => ['rojo=congo>t61'];

        yield 'the backslash character' => ['rojo=congo\t61'];

        yield 'the brace characters' => ['rojo=v{a}'];

        yield 'the bracket characters' => ['rojo=v[a]v]b'];

        yield 'the space inside the value' => ['rojo=a b c'];

        yield 'the whitespace before the comma' => ['a=1 , b=2'];

        yield 'the tab separator after the comma' => ["a=1,\tb=2"];

        yield 'the value at its own bound' => ['a=' . str_repeat('v', 256)];

        yield 'the list at its own bound' => [str_repeat('k=v,', 31) . 'k=v'];

        yield 'the vendor list the suite always carried' => ['vendor1=alpha,vendor2=beta,rojo=00f067aa0ba902b7'];
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
        // The members stay grammar-valid (each value within the spec's
        // own 256-character bound) so it is the ceiling that refuses,
        // not the grammar.
        $this->assertRejected(
            static fn (): CorrelationIdentity => new CorrelationIdentity(
                self::TRACE_ID,
                self::SPAN_ID,
                '01',
                'k=' . str_repeat('a', 256) . ',k=' . str_repeat('a', 256) . ',k=' . str_repeat('a', 256),
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
