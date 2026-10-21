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
}
