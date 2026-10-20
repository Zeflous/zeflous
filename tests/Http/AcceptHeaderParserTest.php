<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\AcceptHeaderParser;

/**
 * Direct battery for the Accept header parser: media-range grammar,
 * quality folding, the character-scale length bound, and the multibyte
 * fold behaviour the header's own vocabulary makes observable.
 *
 * @internal
 */
#[CoversClass(AcceptHeaderParser::class)]
final class AcceptHeaderParserTest extends TestCase
{
    public function testQualitiesFoldRepeatsAndSkipInvalidMediaRanges(): void
    {
        self::assertSame(
            ['text/html' => 1.0, 'application/xhtml+xml' => 0.9],
            AcceptHeaderParser::qualities('text/html,application/xhtml+xml;q=0.9'),
        );
        self::assertSame(
            ['text/html' => 0.9],
            AcceptHeaderParser::qualities('text/html;q=0.9, text/html;q=0.1'),
        );
        self::assertSame(
            ['text/html' => 1.0],
            AcceptHeaderParser::qualities('not-a-media-type, text/html'),
        );
        self::assertSame(
            ['b/c' => 0.5],
            AcceptHeaderParser::qualities('b/c; q=0.5'),
        );
        self::assertSame(
            ['b/c' => 0.0],
            AcceptHeaderParser::qualities('b/c;q=10'),
        );
    }

    public function testTheKelvinFoldJoinsTheMediaTypeVocabulary(): void
    {
        self::assertSame(
            ['texk/html' => 1.0],
            AcceptHeaderParser::qualities('tex' . "\u{212A}" . '/html'),
        );
    }

    public function testTheHeaderIsBoundedAtEightThousandOneHundredNinetyTwoCharacters(): void
    {
        self::assertSame(
            ['b/cde' => 1.0],
            AcceptHeaderParser::qualities(str_repeat('a', 8186) . ',b/cdex'),
        );
    }

    public function testTheBoundCountsCharactersNotBytes(): void
    {
        self::assertSame(
            ['b/c' => 1.0],
            AcceptHeaderParser::qualities(str_repeat('é', 10) . str_repeat('a', 8178) . ',b/c'),
        );
    }
}
