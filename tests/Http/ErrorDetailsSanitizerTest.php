<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ErrorDetailsSanitizer;

/**
 * Pins the developer-detail sanitizer's own bounds: the fifty-entry cap on
 * the detail map, the two-thousand-and-forty-eight-character clamp on every
 * string value, and the depth-four truncation marker. The renderer and the
 * problem-document factory pin their own compositions of these primitives
 * (the golden-master page and envelope tests); this battery pins the
 * primitives themselves at both sides of every bound.
 *
 * @internal
 */
#[CoversClass(ErrorDetailsSanitizer::class)]
final class ErrorDetailsSanitizerTest extends TestCase
{
    public function testCapsTheDetailEntriesAtFifty(): void
    {
        $details = [];
        foreach (range(1, 60) as $ordinal) {
            $details['k' . $ordinal] = $ordinal;
        }

        $safe = ErrorDetailsSanitizer::sanitize($details);

        self::assertCount(50, $safe);
        self::assertSame('k1', array_key_first($safe));
        self::assertSame('k50', array_key_last($safe));
        self::assertArrayNotHasKey('k51', $safe);
        self::assertSame(50, $safe['k50']);
    }

    public function testBoundsEachStringValueAtTwoThousandAndFortyEightCharacters(): void
    {
        $safe = ErrorDetailsSanitizer::sanitize(['long' => str_repeat('a', 3000)]);

        self::assertSame(str_repeat('a', 2048), $safe['long']);
    }

    /**
     * The value clamp cuts on character boundaries: a string whose two
     * thousand and forty-eighth character is the two-byte e-acute keeps
     * that character whole, so a value can never surface with its tail
     * starting mid-character as an invalid byte sequence.
     */
    public function testTheValueClampCutsOnCharacterBoundaries(): void
    {
        $value = str_repeat('a', 2047) . 'é' . str_repeat('b', 64);

        $safe = ErrorDetailsSanitizer::sanitize(['boundary' => $value]);

        self::assertSame(str_repeat('a', 2047) . 'é', $safe['boundary']);
    }

    public function testTruncatesAtTheDepthBound(): void
    {
        self::assertSame(
            ['_truncated' => true],
            ErrorDetailsSanitizer::sanitize(['nested' => ['deep' => 'value']], 4),
        );
    }
}
