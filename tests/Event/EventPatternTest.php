<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\EventPattern;

/**
 * @internal
 */
#[CoversClass(EventPattern::class)]
final class EventPatternTest extends TestCase
{
    public function testTheCanonicalSpellingIsKeptByteIdentical(): void
    {
        self::assertSame('user.*', new EventPattern('user.*')->spelling());
        self::assertSame('Order.Created.*', new EventPattern('Order.Created.*')->spelling());
    }

    #[DataProvider('provideTheGrammarAcceptsNamePrefixedTrailingStarsCases')]
    public function testTheGrammarAcceptsNamePrefixedTrailingStars(string $pattern): void
    {
        self::assertSame($pattern, new EventPattern($pattern)->spelling());
    }

    /**
     * @return iterable<string, list<string>>
     */
    public static function provideTheGrammarAcceptsNamePrefixedTrailingStarsCases(): iterable
    {
        yield 'single segment prefix' => ['user.*'];

        yield 'multi segment prefix' => ['Order.Created.*'];

        yield 'digits and dashes' => ['order-2.paid_by.*'];

        yield 'underscored prefix' => ['user_session.*'];
    }

    public function testAPatternOfTheMaximalLengthIsAccepted(): void
    {
        $maximal = str_repeat('a', 62) . '.*';

        self::assertSame($maximal, new EventPattern($maximal)->spelling());
    }

    public function testAnEmptyPatternIsRefused(): void
    {
        try {
            new EventPattern('');
            self::fail('An empty pattern must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event pattern must be a non-empty string of at most 64'
                . ' characters, got a length of 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnOverlongPatternIsRefusedWithItsOwnLengthSpelled(): void
    {
        try {
            new EventPattern('user.' . str_repeat('a', 60) . '.*');
            self::fail('An overlong pattern must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event pattern must be a non-empty string of at most 64'
                . ' characters, got a length of 67.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnOverlongMultibytePatternAnswersItsCharacterCount(): void
    {
        // The ceiling counts characters, not bytes: a hundred two-byte
        // letters are a hundred characters over it, never two hundred.
        try {
            new EventPattern(str_repeat('é', 100) . '.*');
            self::fail('An overlong pattern must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event pattern must be a non-empty string of at most 64'
                . ' characters, got a length of 102.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    #[DataProvider('provideTheGrammarRefusesEveryOtherStarSpellingCases')]
    public function testTheGrammarRefusesEveryOtherStarSpelling(string $pattern, string $message): void
    {
        try {
            new EventPattern($pattern);
            self::fail("The pattern '{$pattern}' must be refused.");
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame($message, $invalidArgumentException->getMessage());
        }
    }

    /**
     * @return iterable<string, list<string>>
     */
    public static function provideTheGrammarRefusesEveryOtherStarSpellingCases(): iterable
    {
        yield 'star mid pattern' => [
            'user.*.created',
            "An event pattern must end in '.*' (the wildcard star owns the"
            . " final segment alone), got 'user.*.created'.",
        ];

        yield 'star glued to a word' => [
            'user*',
            "An event pattern must end in '.*' (the wildcard star owns the"
            . " final segment alone), got 'user*'.",
        ];

        yield 'trailing dot only' => [
            'user.',
            "An event pattern must end in '.*' (the wildcard star owns the"
            . " final segment alone), got 'user.'.",
        ];

        yield 'double star tail' => [
            'user.**',
            "An event pattern must end in '.*' (the wildcard star owns the"
            . " final segment alone), got 'user.**'.",
        ];

        yield 'empty segment before the star' => [
            'user..*',
            "An event pattern's prefix must name the segment the star stands"
            . " behind, got 'user..' -- an empty segment matches no event.",
        ];

        yield 'star with a suffix' => [
            'user.*x',
            "An event pattern must end in '.*' (the wildcard star owns the"
            . " final segment alone), got 'user.*x'.",
        ];

        yield 'bare star' => [
            '*',
            "An event pattern must end in '.*' (the wildcard star owns the"
            . " final segment alone), got '*'.",
        ];

        yield 'star alone behind the dot' => [
            '.*',
            "An event pattern's prefix must start with a letter and continue"
            . " with letters, digits, dots, dashes or underscores, got ''.",
        ];

        yield 'digit-first prefix' => [
            '2users.*',
            "An event pattern's prefix must start with a letter and continue"
            . " with letters, digits, dots, dashes or underscores, got '2users'.",
        ];

        yield 'space in prefix' => [
            'user created.*',
            "An event pattern's prefix must start with a letter and continue"
            . ' with letters, digits, dots, dashes or underscores, got'
            . " 'user created'.",
        ];

        yield 'multibyte letters are outside the grammar' => [
            str_repeat('é', 33) . '.*',
            "An event pattern's prefix must start with a letter and continue"
            . ' with letters, digits, dots, dashes or underscores, got '
            . "'" . str_repeat('é', 33) . "'.",
        ];
    }

    #[DataProvider('provideMatchingIsContinuationPastThePrefixCases')]
    public function testMatchingIsContinuationPastThePrefix(string $pattern, string $name, bool $expected): void
    {
        self::assertSame(
            $expected,
            new EventPattern($pattern)->matches(new EventName($name)),
            "{$pattern} vs {$name}",
        );
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideMatchingIsContinuationPastThePrefixCases(): iterable
    {
        yield 'one segment deeper' => ['user.*', 'user.created', true];

        yield 'two segments deeper' => ['user.*', 'user.updated.late', true];

        yield 'the prefix itself is not past the dot' => ['user.*', 'user', false];

        yield 'a different prefix' => ['user.*', 'order.paid', false];

        yield 'a longer shared spelling' => ['user.*', 'usergroup.created', false];

        yield 'multi segment prefix matches its own depth' => ['Order.Created.*', 'Order.Created.v2', true];

        yield 'multi segment prefix refuses the shallower name' => ['Order.Created.*', 'Order.Created', false];

        yield 'multi segment prefix refuses the sibling branch' => ['Order.Created.*', 'Order.Paid.v2', false];
    }
}
