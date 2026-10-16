<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\StrictToggles;

/**
 * The strict boolean reading, on its own terms: the closed vocabulary's
 * every spelling, its case fold, and the fail-closed rejection with the
 * knob named -- the seam both config-driven security families read their
 * toggles through.
 *
 * @internal
 */
#[CoversClass(StrictToggles::class)]
final class StrictTogglesTest extends TestCase
{
    /**
     * @dataProvider provideTheVocabularysTrueSpellingsFoldToTrueCases
     */
    #[DataProvider('provideTheVocabularysTrueSpellingsFoldToTrueCases')]
    public function testTheVocabularysTrueSpellingsFoldToTrue(string $word): void
    {
        self::assertTrue(StrictToggles::read($word, 'security.rate_limit enabled'));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideTheVocabularysTrueSpellingsFoldToTrueCases(): iterable
    {
        yield '1' => ['1'];

        yield 'true' => ['true'];

        yield 'on' => ['on'];

        yield 'yes' => ['yes'];

        yield 'folded TRUE' => ['TRUE'];

        yield 'folded On' => ['On'];

        yield 'folded YeS' => ['YeS'];
    }

    /**
     * @dataProvider provideTheVocabularysFalseSpellingsFoldToFalseCases
     */
    #[DataProvider('provideTheVocabularysFalseSpellingsFoldToFalseCases')]
    public function testTheVocabularysFalseSpellingsFoldToFalse(string $word): void
    {
        self::assertFalse(StrictToggles::read($word, 'security.rate_limit enabled'));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideTheVocabularysFalseSpellingsFoldToFalseCases(): iterable
    {
        yield '0' => ['0'];

        yield 'false' => ['false'];

        yield 'off' => ['off'];

        yield 'no' => ['no'];

        yield 'empty' => [''];

        yield 'folded FALSE' => ['FALSE'];

        yield 'folded Off' => ['Off'];

        yield 'folded No' => ['No'];
    }

    public function testNativeBooleansPassThroughUntouched(): void
    {
        self::assertTrue(StrictToggles::read(true, 'security.rate_limit enabled'));
        self::assertFalse(StrictToggles::read(false, 'security.rate_limit enabled'));
    }

    /**
     * @dataProvider provideEverythingElseHaltsWithTheKnobNamedCases
     */
    #[DataProvider('provideEverythingElseHaltsWithTheKnobNamedCases')]
    public function testEverythingElseHaltsWithTheKnobNamed(mixed $value, string $type): void
    {
        try {
            StrictToggles::read($value, 'security.rate_limit enabled');
            self::fail('A value outside the vocabulary must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit enabled knob must be a boolean or one of the boolean string '
                . 'spellings (true/false, on/off, yes/no, 1/0), ' . $type . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<array{mixed, string}>
     */
    public static function provideEverythingElseHaltsWithTheKnobNamedCases(): iterable
    {
        yield 'an unknown word' => ['maybe', 'string'];

        yield 'a mistyped word' => ['ture', 'string'];

        yield 'a partial word' => ['TR', 'string'];

        yield 'an integer' => [1, 'int'];

        yield 'a float' => [1.5, 'float'];

        yield 'null' => [null, 'null'];

        yield 'an array' => [[], 'array'];
    }
}
