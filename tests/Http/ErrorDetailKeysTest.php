<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ErrorDetailKeys;

/**
 * Pins the developer-detail key gate's own grammar: the bounded plain-ASCII
 * vocabulary the sanitizer's entry loop composes but never isolates. The
 * anchored shape is the contract -- a key is accepted only when the whole
 * string is one to eighty ASCII letters, digits, underscores, dots or
 * hyphens, so every other shape (a non-string, the empty string, an
 * over-long key, a stray byte anywhere, a trailing newline) is refused.
 * The cases travel through providers because the gate is pure enough for
 * the analyser to fold a literal call on the spot.
 *
 * @internal
 */
#[CoversClass(ErrorDetailKeys::class)]
final class ErrorDetailKeysTest extends TestCase
{
    #[DataProvider('provideAcceptsTheBoundedAsciiVocabularyCases')]
    public function testAcceptsTheBoundedAsciiVocabulary(mixed $key): void
    {
        self::assertTrue(ErrorDetailKeys::accepts($key));
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string}>
     */
    public static function provideAcceptsTheBoundedAsciiVocabularyCases(): iterable
    {
        yield 'underscores' => ['request_id'];

        yield 'hyphens and digits' => ['order-42'];

        yield 'a single letter' => ['a'];

        yield 'dots' => ['line.3'];

        yield 'exactly eighty characters' => [str_repeat('k', 80)];
    }

    #[DataProvider('provideRefusesEveryShapeOutsideTheVocabularyCases')]
    public function testRefusesEveryShapeOutsideTheVocabulary(mixed $key): void
    {
        self::assertFalse(ErrorDetailKeys::accepts($key));
    }

    /**
     * A stray byte before a valid tail is still a refusal: the caret
     * anchor makes the grammar whole-string, so the space inside
     * "valid key" (or any other byte outside the vocabulary) cannot be
     * skipped past to match the suffix. A trailing newline is a refusal
     * too, not an invisible eighty-first character: the dollar anchor
     * matches the end of the string itself, not the position just before
     * a final newline.
     *
     * @return iterable<non-empty-string, array{mixed}>
     */
    public static function provideRefusesEveryShapeOutsideTheVocabularyCases(): iterable
    {
        yield 'an int' => [0];

        yield 'null' => [null];

        yield 'an array' => [['request_id']];

        yield 'a bool' => [true];

        yield 'the empty string' => [''];

        yield 'the eighty-first character' => [str_repeat('k', 81)];

        yield 'a stray byte inside the key' => ['valid key'];

        yield 'a stray byte ahead of the key' => ['!valid'];

        yield 'a stray byte after the key' => ['valid!'];

        yield 'a trailing newline' => ["request_id\n"];
    }
}
