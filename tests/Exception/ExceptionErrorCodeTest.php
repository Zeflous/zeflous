<?php

declare(strict_types=1);

namespace Zef\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Exception\ExceptionErrorCode;

/**
 * @internal
 */
#[CoversClass(ExceptionErrorCode::class)]
final class ExceptionErrorCodeTest extends TestCase
{
    /**
     * The named spelling the fold answers: the short name minus its
     * `Exception` suffix, camel boundaries marked, and everything the
     * uppercase census refuses folded away -- the capitals and digits
     * survive (`U_N` is the pinned spelling of the grammar).
     */
    public function testTheShortNameFoldsToItsCapitalSkeleton(): void
    {
        self::assertSame('U_N', ExceptionErrorCode::for('Zef\Framework\Excëption\UnicodeNamespaceException'));
        self::assertSame('C_B', ExceptionErrorCode::for('App\Container\ContainerBootException'));
        self::assertSame('E_T_M', ExceptionErrorCode::for('Http\ETagMiddlewareException'));
        self::assertSame('W', ExceptionErrorCode::for('Window'));
    }

    public function testARunOfUnderscoresCollapsesToOne(): void
    {
        self::assertSame('B_C_D', ExceptionErrorCode::for('App\B-C__D'));
    }

    public function testAFoldThatWouldNotStartALetterOrDigitAnswersTheFallback(): void
    {
        self::assertSame('FRAMEWORK_ERROR', ExceptionErrorCode::for('123'));
        self::assertSame('FRAMEWORK_ERROR', ExceptionErrorCode::for(''));
    }

    public function testTheCodeCeilsAtSixtyFourBytes(): void
    {
        $class = str_repeat('A', 80) . 'Exception';

        self::assertSame('A' . str_repeat('_A', 31), ExceptionErrorCode::for($class));
    }

    /**
     * The anonymous spelling: one digest over the parent and the
     * engine name's own tail -- never the absolute path, and the same
     * tree checked out elsewhere answers the same code.
     */
    public function testTheAnonymousClassAnswersTheStableDigestCode(): void
    {
        $first = 'Zef\Framework\Exception\ZefException@anonymous/home/deploy-a/src/File.php$57abc';
        $second = 'Zef\Framework\Exception\ZefException@anonymous/home/deploy-b/src/File.php$57abc';

        self::assertMatchesRegularExpression('/^ANONYMOUS_[0-9A-F]{8}$/', ExceptionErrorCode::for($first));
        self::assertSame(ExceptionErrorCode::for($first), ExceptionErrorCode::for($second));
    }

    public function testSiblingFilesDeclaringAtTheSameLineAnswerDifferentCodes(): void
    {
        $first = 'Zef\Framework\Exception\ZefException@anonymous/src/One.php$57abc';
        $second = 'Zef\Framework\Exception\ZefException@anonymous/src/Two.php$57abc';

        self::assertNotSame(ExceptionErrorCode::for($first), ExceptionErrorCode::for($second));
    }

    public function testAnAnonymousNameWithoutATailStillAnswersADigest(): void
    {
        $bare = 'Zef\Framework\Exception\ZefException@anonymous';

        self::assertMatchesRegularExpression('/^ANONYMOUS_[0-9A-F]{8}$/', ExceptionErrorCode::for($bare));
    }

    /**
     * The short-name cut is the multibyte one: a namespace whose bytes
     * and characters disagree moves the byte cut a character early,
     * onto the capital before the separator, and the fold would read
     * that capital into the skeleton. The pinned answer keeps the cut
     * after the separator exactly.
     */
    public function testTheShortNameCutStaysAfterTheSeparatorInCharacters(): void
    {
        self::assertSame('Z', ExceptionErrorCode::for('BéáR\ZooException'));
    }

    /**
     * The letter-start census is anchored to the first character: a
     * fold that starts with a digit but carries a letter later still
     * answers the fallback, because a code the census accepts starts
     * with a letter -- never merely contains one.
     */
    public function testAFoldStartingWithADigitAnswersTheFallbackEvenWithALaterLetter(): void
    {
        self::assertSame('FRAMEWORK_ERROR', ExceptionErrorCode::for('1A'));
    }

    /**
     * The anonymous digest is pinned to its exact identity: the parent
     * before the marker, the separator, and the tail after the LAST
     * slash -- multibyte included, so every offset, operand and
     * rearrangement of the fold answers a different digest and the
     * battery sees it.
     */
    public function testTheAnonymousDigestIsPinnedToItsExactIdentity(): void
    {
        $name = 'Zef\Framework\Exception\ZefException@anonymous/päth/File.php$57abc';

        $expected = 'ANONYMOUS_' . mb_strtoupper(
            hash('crc32b', 'Zef\Framework\Exception\ZefException|File.php$57abc'),
        );

        self::assertSame($expected, ExceptionErrorCode::for($name));
    }
}
