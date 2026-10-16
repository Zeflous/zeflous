<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Telemetry\OtlpJsonPayloadEncoder;

/**
 * The OTLP/JSON attribute encoder, on its own terms: the KeyValue
 * list's shape (the map's order, the key/value pair spelling), and
 * every typed arm the protocol's value model carries -- the
 * boolean, the integer as the string the 64-bit field spells, the
 * float, the array of encoded elements with the keys the protocol
 * does not carry, and the scalar fallback (the clamped string, the
 * debug type for a value that is not a string at all).
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
}
