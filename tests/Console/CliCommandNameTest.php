<?php

declare(strict_types=1);

namespace Zef\Test\Console;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Console\CliCommandName;

/**
 * The command name grammar: the `route:list` convention -- lowercase
 * labels, hyphens inside a label, at most one colon, bounded whole --
 * and every refusal's sentence.
 *
 * @internal
 */
#[CoversClass(CliCommandName::class)]
final class CliCommandNameTest extends TestCase
{
    #[DataProvider('provideTheConventionSpellingsAnswerCases')]
    public function testTheConventionSpellingsAnswer(string $spelling): void
    {
        self::assertSame($spelling, new CliCommandName($spelling)->toString());
        self::assertSame($spelling, new CliCommandName($spelling)->name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTheConventionSpellingsAnswerCases(): iterable
    {
        yield 'the framework convention' => ['route:list'];

        yield 'a single label' => ['ping'];

        yield 'hyphens inside a label' => ['migrations:rollback-all'];

        yield 'a hyphenated single label' => ['error-report'];

        yield 'digits inside a label' => ['shop:report2'];
    }

    #[DataProvider('provideTheGrammarRefusesEveryWrongSpellingCases')]
    public function testTheGrammarRefusesEveryWrongSpelling(string $spelling, string $sentence): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($sentence);

        new CliCommandName($spelling);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTheGrammarRefusesEveryWrongSpellingCases(): iterable
    {
        yield 'empty' => ['', 'The command name must not be empty.'];

        yield 'uppercase label' => ['Route:list', 'must be lowercase labels'];

        yield 'a space' => ['shop ping', 'must be lowercase labels'];

        yield 'two colons' => ['shop:sub:ping', 'at most one colon'];

        yield 'a leading colon' => [':ping', 'must be lowercase labels'];

        yield 'a trailing colon' => ['shop:', 'must be lowercase labels'];

        yield 'a leading hyphen' => ['-ping', 'must be lowercase labels'];

        yield 'a leading digit' => ['2shop:ping', 'must be lowercase labels'];

        yield 'an underscore' => ['shop_ping', 'must be lowercase labels'];

        yield 'a control byte' => ["shop:pi\x01ng", 'must be lowercase labels'];

        yield 'too long' => [
            'shop:' . str_repeat('a', 60),
            'is longer than the 64 characters',
        ];
    }

    public function testAMultibyteNameIsJudgedByItsCharactersNotItsBytes(): void
    {
        // Sixty-four characters at a hundred and twenty-three bytes: the
        // length bound counts characters, so the multibyte spelling
        // reaches the GRAMMAR refusal -- never the byte-length refusal an
        // ASCII-only fold could shadow it with (the retry-messages lane's
        // own Unicode lesson, replayed for the console lane).
        $spelling = 'shop:' . str_repeat('é', 59);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('must be lowercase labels');

        new CliCommandName($spelling);
    }

    public function testTheGrammarRefusalCarriesItsWholeSentence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            "The command name 'Shop:Ping' must be lowercase labels of letters, digits and hyphens,"
            . " separated by at most one colon (the 'route:list' convention).",
        );

        new CliCommandName('Shop:Ping');
    }

    public function testTheSixtyFourthCharacterIsStillWelcome(): void
    {
        $spelling = 'shop:' . str_repeat('a', 59);

        self::assertSame($spelling, new CliCommandName($spelling)->toString());
    }
}
