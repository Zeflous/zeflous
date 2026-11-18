<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\EventName;

/**
 * Pins the name grammar the whole event vocabulary addresses its
 * participants by: the dot-, dash- and underscore-separated
 * spellings a definition, a subscription and a dispatch all
 * accept, and the refusals -- empty, overlong, wrongly-opened or
 * wrongly-continued -- that keep every name printable and
 * wildcard-unambiguous.
 *
 * @internal
 */
#[CoversClass(EventName::class)]
final class EventNameTest extends TestCase
{
    public function testTheCanonicalSpellingIsCarriedVerbatim(): void
    {
        self::assertSame('user.created', new EventName('user.created')->spelling());
    }

    public function testASingleLetterIsASpelling(): void
    {
        self::assertSame('a', new EventName('a')->spelling());
    }

    public function testTheFullAlphabetOfSeparatorsIsAccepted(): void
    {
        $name = 'Order.paid-v2_replayed';

        self::assertSame($name, new EventName($name)->spelling());
    }

    public function testTheMaximumLengthIsAccepted(): void
    {
        $name = 'u' . str_repeat('x', 63);

        self::assertSame($name, new EventName($name)->spelling());
    }

    public function testAnEmptySpellingIsRefused(): void
    {
        try {
            new EventName('');
            self::fail('The empty spelling was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event name must be a non-empty string of at most 64'
                . ' characters, got a length of 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnOverlongSpellingIsRefused(): void
    {
        try {
            new EventName('u' . str_repeat('x', 64));
            self::fail('The overlong spelling was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event name must be a non-empty string of at most 64'
                . ' characters, got a length of 65.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASpellingOpeningWithADigitIsRefused(): void
    {
        try {
            new EventName('2fa.enabled');
            self::fail('The digit-opening spelling was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event name must start with a letter and continue with'
                . " letters, digits, dots, dashes or underscores, got '2fa.enabled'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASpellingOpeningWithADotIsRefused(): void
    {
        try {
            new EventName('.user.created');
            self::fail('The dot-opening spelling was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event name must start with a letter and continue with'
                . " letters, digits, dots, dashes or underscores, got '.user.created'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASpellingWithWhitespaceIsRefused(): void
    {
        try {
            new EventName('user created');
            self::fail('The whitespace spelling was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event name must start with a letter and continue with'
                . " letters, digits, dots, dashes or underscores, got 'user created'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASpellingWithControlBytesIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EventName("user\ncreated");
    }

    /**
     * The #786 anchor pin: PCRE's bare `$` also matches immediately
     * before a final newline, so a trailing one was grammar-legal while
     * the canonical spelling silently carried a control byte -- and it
     * slipped through the length ceiling too (`a` x63 + `\n` is 64
     * characters, ceiling-legal, so the grammar was the only guard
     * left). The `D` modifier pins the anchor to the true end.
     */
    public function testATrailingNewlineIsRefusedByTheGrammarAndTheLengthCeilingBypass(): void
    {
        try {
            new EventName("user.created\n");
            self::fail('A trailing newline is not a grammar-legal spelling.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event name must start with a letter and continue with'
                . " letters, digits, dots, dashes or underscores, got 'user.created\n'.",
                $invalidArgumentException->getMessage(),
            );
        }

        try {
            new EventName(str_repeat('a', 63) . "\n");
            self::fail('A ceiling-legal spelling ending in a newline is not grammar-legal.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString(
                "got 'a",
                $invalidArgumentException->getMessage(),
                'the ceiling-legal spelling is refused by the grammar alone',
            );
        }
    }

    public function testAMultibyteSpellingWithinTheCharacterCeilingIsRefusedByTheGrammar(): void
    {
        // 33 two-byte characters: 33 characters (within the ceiling
        // the character count spells) but 66 bytes -- a byte-length
        // ceiling would refuse it here as overlong, and the refusal
        // this test pins is the grammar's, not the length's.
        $overlongInBytes = str_repeat("\u{fc}", 33);

        try {
            new EventName($overlongInBytes);
            self::fail('The multibyte spelling was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event name must start with a letter and continue with'
                . " letters, digits, dots, dashes or underscores, got '{$overlongInBytes}'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMultibyteSpellingOverTheCharacterCeilingIsRefusedByTheCount(): void
    {
        // 65 two-byte characters: 65 characters (over the ceiling)
        // but 130 bytes -- the refusal names the CHARACTER count.
        $overlongInCharacters = str_repeat("\u{fc}", 65);

        try {
            new EventName($overlongInCharacters);
            self::fail('The character-overlong multibyte spelling was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event name must be a non-empty string of at most 64'
                . ' characters, got a length of 65.',
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
