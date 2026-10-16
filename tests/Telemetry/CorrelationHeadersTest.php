<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CorrelationHeaders;
use Zef\Framework\Telemetry\CorrelationIdentity;

/**
 * The correlation transport pair, on its own terms: the exact-width
 * parent, the bounded companion, the wire accounting -- the shape
 * one hop's headers carry between the pair's two ends.
 *
 * @internal
 */
#[CoversClass(CorrelationHeaders::class)]
final class CorrelationHeadersTest extends TestCase
{
    private const string TRACE_PARENT = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    public function testCarriesThePairAndAnswersTheWireAccounting(): void
    {
        $correlationHeaders = new CorrelationHeaders(self::TRACE_PARENT, 'vendor=value');

        self::assertSame(self::TRACE_PARENT, $correlationHeaders->traceParent);
        self::assertSame('vendor=value', $correlationHeaders->traceState);
        self::assertSame(55 + mb_strlen('vendor=value'), $correlationHeaders->encodedBytes());
    }

    public function testTheCompanionIsOptional(): void
    {
        $correlationHeaders = new CorrelationHeaders(self::TRACE_PARENT, null);

        self::assertNull($correlationHeaders->traceState);
        self::assertSame(55, $correlationHeaders->encodedBytes());
    }

    public function testRejectsTheParentThatIsNotTheExactWidth(): void
    {
        try {
            new CorrelationHeaders(mb_substr(self::TRACE_PARENT, 0, 54), null);
            self::fail('A short traceparent must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        try {
            new CorrelationHeaders(self::TRACE_PARENT . '0', null);
            self::fail('A long traceparent must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }

    public function testTheCompanionAtItsCeilingShips(): void
    {
        $atCeiling = str_repeat('k', CorrelationIdentity::MAX_TRACESTATE_BYTES);

        $correlationHeaders = new CorrelationHeaders(self::TRACE_PARENT, $atCeiling);

        self::assertSame($atCeiling, $correlationHeaders->traceState);
        self::assertSame(
            CorrelationIdentity::MAX_TRACEPARENT_BYTES + CorrelationIdentity::MAX_TRACESTATE_BYTES,
            $correlationHeaders->encodedBytes(),
        );
    }

    public function testRejectsTheCompanionThatExceedsItsCeiling(): void
    {
        try {
            new CorrelationHeaders(
                self::TRACE_PARENT,
                str_repeat('k', CorrelationIdentity::MAX_TRACESTATE_BYTES + 1),
            );
            self::fail('An oversized tracestate must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }

    public function testTheParentsWidthCountsCharactersNotBytes(): void
    {
        // Fifty-five characters whose bytes are fifty-six: the W3C
        // render's width reads the characters as ported, and the
        // two-byte long s is the one spelling that tells the folds
        // apart.
        $parent = mb_substr(self::TRACE_PARENT, 0, CorrelationIdentity::MAX_TRACEPARENT_BYTES - 1) . "\u{017F}";

        $correlationHeaders = new CorrelationHeaders($parent, null);

        self::assertSame(
            CorrelationIdentity::MAX_TRACEPARENT_BYTES,
            mb_strlen($correlationHeaders->traceParent),
        );
        self::assertSame(
            CorrelationIdentity::MAX_TRACEPARENT_BYTES,
            $correlationHeaders->encodedBytes(),
            'the accounting counts the characters the width counted',
        );
    }

    public function testTheCompanionsCeilingCountsCharactersNotBytes(): void
    {
        $atCeiling = 'v=' . str_repeat("\u{017F}", CorrelationIdentity::MAX_TRACESTATE_BYTES - 2);

        $correlationHeaders = new CorrelationHeaders(self::TRACE_PARENT, $atCeiling);

        self::assertSame(
            CorrelationIdentity::MAX_TRACEPARENT_BYTES + CorrelationIdentity::MAX_TRACESTATE_BYTES,
            $correlationHeaders->encodedBytes(),
            'the accounting counts the characters the ceiling counted',
        );
    }
}
