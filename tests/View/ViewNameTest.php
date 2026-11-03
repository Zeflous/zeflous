<?php

declare(strict_types=1);

namespace Zef\Test\View;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\View\ViewName;

/**
 * Pins the name grammar's whole refusal table: the empty name, the
 * byte ceilings (counted in bytes, not characters -- the multibyte
 * arm), the empty segment's three spellings, the dot-led family the
 * segment class refuses by shape (the traversal, the
 * self-reference, the dotfile), and every character class escape --
 * plus the exact boundaries both ceilings accept and the canonical
 * spelling the identity carries.
 *
 * @internal
 */
#[CoversClass(ViewName::class)]
final class ViewNameTest extends TestCase
{
    public function testConstructsTheSimpleSegment(): void
    {
        self::assertSame('about', (new ViewName('about'))->spelling());
    }

    public function testConstructsNestedSegments(): void
    {
        self::assertSame('users/profile', (new ViewName('users/profile'))->spelling());
    }

    public function testConstructsTheFullSegmentGrammar(): void
    {
        self::assertSame(
            'A.b-c_d0/E9.f-g_h',
            (new ViewName('A.b-c_d0/E9.f-g_h'))->spelling(),
        );
    }

    public function testConstructsTheWholeNameByteCeilingExactly(): void
    {
        $name = str_repeat('a', 4096);

        self::assertSame($name, (new ViewName($name))->spelling());
    }

    public function testConstructsTheSegmentByteCeilingExactly(): void
    {
        $name = str_repeat('b', 255);

        self::assertSame($name, (new ViewName($name))->spelling());
    }

    public function testRefusesTheEmptyName(): void
    {
        try {
            new ViewName('');
            self::fail('The empty name was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name must not be empty.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesANameOverTheWholeByteCeiling(): void
    {
        $name = str_repeat('a', 4097);

        try {
            new ViewName($name);
            self::fail('The oversized name was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name must be at most 4096 bytes, got 4097 bytes.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesAMultibyteNameOverTheWholeByteCeiling(): void
    {
        $name = str_repeat('é', 2049);

        try {
            new ViewName($name);
            self::fail('The multibyte oversized name was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name must be at most 4096 bytes, got 4098 bytes.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesASegmentOverTheByteCeiling(): void
    {
        $name = 'a/' . str_repeat('b', 256);

        try {
            new ViewName($name);
            self::fail('The oversized segment was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be at most 255 bytes, got 256 bytes.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesAMultibyteSegmentOverTheByteCeiling(): void
    {
        $name = str_repeat('é', 128);

        try {
            new ViewName($name);
            self::fail('The multibyte oversized segment was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be at most 255 bytes, got 256 bytes.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesTheLeadingSeparator(): void
    {
        try {
            new ViewName('/about');
            self::fail('The leading separator was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name must not carry an empty segment.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesTheTrailingSeparator(): void
    {
        try {
            new ViewName('about/');
            self::fail('The trailing separator was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name must not carry an empty segment.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesTheDoubledSeparator(): void
    {
        try {
            new ViewName('users//profile');
            self::fail('The doubled separator was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name must not carry an empty segment.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesTheTraversalSegment(): void
    {
        try {
            new ViewName('..');
            self::fail('The traversal segment was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got '..'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesTheSelfReferenceSegment(): void
    {
        try {
            new ViewName('.');
            self::fail('The self-reference segment was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got '.'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesTheDotfileSegment(): void
    {
        try {
            new ViewName('.env');
            self::fail('The dotfile segment was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got '.env'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesABackslash(): void
    {
        try {
            new ViewName('a\b');
            self::fail('The backslash was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got 'a\b'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesAColon(): void
    {
        try {
            new ViewName('a:b');
            self::fail('The colon was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got 'a:b'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesANulByte(): void
    {
        try {
            new ViewName("a\0b");
            self::fail('The NUL byte was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got 'a\0b'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesAControlCharacter(): void
    {
        try {
            new ViewName("a\x01b");
            self::fail('The control character was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got 'a\x01b'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesASpace(): void
    {
        try {
            new ViewName('a b');
            self::fail('The space was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got 'a b'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesANonAsciiSegment(): void
    {
        try {
            new ViewName('é');
            self::fail('The non-ASCII segment was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got 'é'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
