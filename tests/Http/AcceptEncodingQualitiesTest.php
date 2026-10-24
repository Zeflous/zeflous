<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\AcceptEncodingQualities;

/**
 * The `Accept-Encoding` grammar, on its own terms: the member walk,
 * the quality arithmetic, the wildcard and identity spellings, the
 * malformed-member skip and the bounded read.
 *
 * @internal
 */
#[CoversClass(AcceptEncodingQualities::class)]
final class AcceptEncodingQualitiesTest extends TestCase
{
    public function testABareCodingAcceptsAtOne(): void
    {
        self::assertSame(['gzip' => 1.0], AcceptEncodingQualities::qualities('gzip'));
    }

    public function testTheQualityParameterReads(): void
    {
        self::assertSame(
            ['gzip' => 0.5, 'deflate' => 1.0],
            AcceptEncodingQualities::qualities('gzip;q=0.5, deflate'),
        );
    }

    public function testASpelledQualityOfZeroIsKeptForTheNegotiatorToRefuse(): void
    {
        self::assertSame(['gzip' => 0.0], AcceptEncodingQualities::qualities('gzip;q=0'));
    }

    public function testTheWildcardAndIdentitySpellingsAreCodingsLikeAnyOther(): void
    {
        self::assertSame(
            ['*' => 1.0, 'identity' => 0.5],
            AcceptEncodingQualities::qualities('*, identity;q=0.500'),
        );
    }

    public function testTheSpellingsFoldToLowercase(): void
    {
        self::assertSame(['gzip' => 1.0], AcceptEncodingQualities::qualities('GZIP'));
    }

    public function testARepeatedCodingKeepsItsHighestQuality(): void
    {
        self::assertSame(
            ['gzip' => 1.0, 'deflate' => 0.25],
            AcceptEncodingQualities::qualities('gzip, deflate;q=0.25, gzip;q=0.5'),
            'the earlier 1.0 survives the later 0.5 -- the map folds to the max, not the last',
        );
    }

    #[DataProvider('provideMalformedMembersAreSkippedCases')]
    public function testMalformedMembersAreSkipped(string $header, string $survivor): void
    {
        $qualities = AcceptEncodingQualities::qualities($header);

        self::assertSame([$survivor => 1.0], $qualities);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideMalformedMembersAreSkippedCases(): iterable
    {
        yield 'empty members between commas' => [',,gzip,,', 'gzip'];

        yield 'a member with forbidden characters' => ['gzip=x, x-gzip', 'x-gzip'];

        yield 'a path-shaped member' => ['text/html, gzip', 'gzip'];
    }

    public function testAnInvalidQualitySpellsZero(): void
    {
        self::assertSame(
            ['gzip' => 0.0],
            AcceptEncodingQualities::qualities('gzip;q=1.5'),
            'a q above 1 is not a quality the grammar accepts, and 0 is the refusal the walk answers',
        );
    }

    public function testAJunkPrefixedQualityIsNotATrailingQuality(): void
    {
        self::assertSame(
            ['gzip' => 0.0],
            AcceptEncodingQualities::qualities('gzip;q=x1'),
            'a value with leading junk is not a quality anchored at the end of the value',
        );
    }

    public function testASpacedQualityIsNotAQuality(): void
    {
        self::assertSame(
            ['gzip' => 0.0],
            AcceptEncodingQualities::qualities('gzip;q= 1'),
            'the grammar anchors its value at the first byte after the equals sign',
        );
    }

    public function testAQualityWithoutValueSpellsZero(): void
    {
        self::assertSame(['gzip' => 0.0], AcceptEncodingQualities::qualities('gzip;q='));
    }

    public function testTheFirstQualityParameterWins(): void
    {
        self::assertSame(
            ['gzip' => 0.25],
            AcceptEncodingQualities::qualities('gzip;q=0.25;q=0.5'),
            "a degenerate member with two q parameters reads its first, the media-range parser's own walk",
        );
    }

    public function testAnEmptyHeaderParsesToTheEmptyMap(): void
    {
        self::assertSame([], AcceptEncodingQualities::qualities(''));
    }

    public function testTheReadIsBounded(): void
    {
        $survivor = 'gzip';
        $padding = str_repeat('/', 9000);

        $qualities = AcceptEncodingQualities::qualities($survivor . ',' . $padding . ', deflate');

        self::assertSame(
            ['gzip' => 1.0],
            $qualities,
            'the member past the 8192-byte ceiling is never parsed, and a truncated token cannot smuggle itself in',
        );
    }

    public function testTheBoundCountsCharactersNotBytes(): void
    {
        $header = 'gzip,' . str_repeat('é', 5000) . ',deflate';

        $qualities = AcceptEncodingQualities::qualities($header);

        self::assertSame(
            ['gzip' => 1.0, 'deflate' => 1.0],
            $qualities,
            'the ceiling is 8192 characters -- a 10 KB header of two-byte characters is within it',
        );
    }

    public function testTheFoldThatLengthensPushesTheBoundOut(): void
    {
        $header = 'gzip,' . str_repeat('İ', 5000) . ',deflate';

        $qualities = AcceptEncodingQualities::qualities($header);

        self::assertSame(
            ['gzip' => 1.0],
            $qualities,
            'a full case fold that lengthens its input carries the tail past the ceiling',
        );
    }
}
