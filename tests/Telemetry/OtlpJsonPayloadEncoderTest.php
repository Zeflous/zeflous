<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Telemetry\OtlpJsonPayloadEncoder;
use Zef\Framework\Telemetry\SpanContext;
use Zef\Framework\Telemetry\SpanData;

/**
 * The OTLP/JSON attribute encoder, on its own terms: the KeyValue
 * list's shape (the map's order, the key/value pair spelling), and
 * every typed arm the protocol's value model carries -- the
 * boolean, the integer as the string the 64-bit field spells, the
 * float, the array of encoded elements with the keys the protocol
 * does not carry, and the scalar fallback (the clamped string, the
 * debug type for a value that is not a string at all) -- plus the
 * span block's own census points: the name reading the same clamp
 * every event name and status message reads (the malformed
 * UTF-8 byte substituted before the wire grammar can refuse it,
 * the over-budget name truncated at its own boundary, the issue
 * #780 finding the batch's survival rides).
 *
 * @internal
 */
#[CoversClass(OtlpJsonPayloadEncoder::class)]
final class OtlpJsonPayloadEncoderTest extends TestCase
{
    public function testTheAttributeMapEncodesAsTheKeyValueListInOrder(): void
    {
        self::assertSame(
            [
                ['key' => 'http.method', 'value' => ['stringValue' => 'GET']],
                ['key' => 'http.status_code', 'value' => ['intValue' => '200']],
            ],
            OtlpJsonPayloadEncoder::attributes([
                'http.method' => 'GET',
                'http.status_code' => 200,
            ]),
        );
    }

    public function testEveryScalarTypeAnswersItsTypedArm(): void
    {
        self::assertSame(
            ['boolValue' => true],
            OtlpJsonPayloadEncoder::attributes(['flag' => true])[0]['value'],
        );
        self::assertSame(
            ['intValue' => '7'],
            OtlpJsonPayloadEncoder::attributes(['count' => 7])[0]['value'],
            'the integer answers the string the protocol spells',
        );
        self::assertSame(
            ['doubleValue' => 1.5],
            OtlpJsonPayloadEncoder::attributes(['ratio' => 1.5])[0]['value'],
        );
    }

    public function testAnArrayValueAnswersTheEncodedElementsWithoutTheirKeys(): void
    {
        self::assertSame(
            [
                'arrayValue' => [
                    'values' => [
                        ['stringValue' => 'db'],
                        ['intValue' => '2'],
                        ['boolValue' => false],
                    ],
                ],
            ],
            OtlpJsonPayloadEncoder::attributes(
                ['options' => ['first' => 'db', 'second' => 2, 'third' => false]],
            )[0]['value'],
        );
    }

    public function testANestedArrayRecurses(): void
    {
        $encoded = OtlpJsonPayloadEncoder::attributes(['nested' => [['inner' => 'value']]])[0]['value'];

        self::assertSame(
            [
                'arrayValue' => [
                    'values' => [
                        [
                            'arrayValue' => [
                                'values' => [
                                    ['stringValue' => 'value'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $encoded,
            'the inner keys drop at every level',
        );
    }

    public function testAnOverBudgetStringClampsThroughTheHygieneUnit(): void
    {
        self::assertSame(
            ['stringValue' => str_repeat('a', 2045) . '…'],
            OtlpJsonPayloadEncoder::attributes(['long' => str_repeat('a', 3000)])[0]['value'],
        );
    }

    public function testAnObjectAnswersItsDebugType(): void
    {
        self::assertSame(
            ['stringValue' => stdClass::class],
            OtlpJsonPayloadEncoder::attributes(['payload' => new stdClass()])[0]['value'],
        );
    }

    public function testASpanNameWithMalformedUtf8AnswersTheCensusPoint(): void
    {
        // The issue #780 repro: one span name carrying a malformed
        // UTF-8 byte rode the payload verbatim -- the transport's
        // throwing encode answered a JsonException the retry lane
        // misread as a transient failure, and the batch that carried
        // the name burned its whole budget before it was lost. The
        // name now reads the same clamp every event name and status
        // message reads: the malformed byte substituted, the answer
        // valid UTF-8, and the wire encode the transport rides never
        // throws again.
        $encoded = OtlpJsonPayloadEncoder::span($this->spanData("bad\xC3\x28name"));
        $name = $encoded['name'];

        self::assertIsString($name, 'the span name answers the string the protocol spells');
        self::assertTrue(mb_check_encoding($name, 'UTF-8'), 'the census answers wire-encodable bytes');
        self::assertSame(9, mb_strlen($name, 'UTF-8'), 'one substitution answers the malformed byte');
        self::assertStringStartsWith('bad', $name);
        self::assertStringEndsWith('(name', $name);

        json_encode($encoded, \JSON_THROW_ON_ERROR);

        self::addToAssertionCount(1);
    }

    public function testAnOverBudgetSpanNameClampsToTheBudgetTheEventNameShares(): void
    {
        $encoded = OtlpJsonPayloadEncoder::span($this->spanData(str_repeat('a', 3000)));

        self::assertSame(
            str_repeat('a', 253) . '…',
            $encoded['name'],
            'the span name clamps through the hygiene unit at the 256 budget the event name reads',
        );
    }

    /**
     * One completed-span record for the span block's own census
     * tests: the minimal shape, every optional arm absent.
     */
    private function spanData(string $name): SpanData
    {
        return new SpanData(
            $name,
            new SpanContext('4bf92f3577b34da6a3ce929d0e0e4736', '00f067aa0ba902b7'),
            null,
            1,
            2,
            3,
            4,
            'UNSET',
            null,
            [],
            [],
        );
    }
}
