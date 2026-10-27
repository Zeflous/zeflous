<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\ListenerIdentity;

/**
 * Pins the durable identity grammar: the letter-first spelling,
 * the 64-character ceiling counted in characters (the multibyte
 * refusals the event name's own ceiling pins), and the refusal
 * messages a misspelled identity answers with.
 *
 * @internal
 */
#[CoversClass(ListenerIdentity::class)]
final class ListenerIdentityTest extends TestCase
{
    public function testAWellFormedIdentityIsAcceptedAndSpelled(): void
    {
        $listenerIdentity = new ListenerIdentity('app.mail.welcome');

        self::assertSame('app.mail.welcome', $listenerIdentity->spelling());
    }

    public function testTheIdentityMayCarryDigitsDotsDashesAndUnderscores(): void
    {
        $listenerIdentity = new ListenerIdentity('a0-b_c.d');

        self::assertSame('a0-b_c.d', $listenerIdentity->spelling());
    }

    public function testTheEmptyIdentityIsRefused(): void
    {
        try {
            new ListenerIdentity('');
            self::fail('The empty identity was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A listener identity must be a non-empty string of at most 64'
                . ' characters, got a length of 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnIdentityPastTheSixtyFourCharacterCeilingIsRefused(): void
    {
        try {
            new ListenerIdentity(str_repeat('a', 65));
            self::fail('The overlong identity was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A listener identity must be a non-empty string of at most 64'
                . ' characters, got a length of 65.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheCeilingIsCountedInCharactersSoAMultibyteOverrunNamesItsOwnLength(): void
    {
        try {
            new ListenerIdentity(str_repeat('ä', 65));
            self::fail('The multibyte overlong identity was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A listener identity must be a non-empty string of at most 64'
                . ' characters, got a length of 65.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheCeilingIsCountedInCharactersNotBytes(): void
    {
        $sixtyFourCharacters = str_repeat('ä', 64);

        try {
            new ListenerIdentity($sixtyFourCharacters);
            self::fail('The multibyte identity was not refused by the grammar.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A listener identity must start with a letter and continue'
                . ' with letters, digits, dots, dashes or underscores, got'
                . " '{$sixtyFourCharacters}'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnIdentityThatDoesNotStartWithALetterIsRefused(): void
    {
        try {
            new ListenerIdentity('0app.mail');
            self::fail('The digit-first identity was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A listener identity must start with a letter and continue'
                . ' with letters, digits, dots, dashes or underscores, got'
                . " '0app.mail'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnIdentityWithWhitespaceIsRefused(): void
    {
        try {
            new ListenerIdentity('app mail');
            self::fail('The spaced identity was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A listener identity must start with a letter and continue'
                . ' with letters, digits, dots, dashes or underscores, got'
                . " 'app mail'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
