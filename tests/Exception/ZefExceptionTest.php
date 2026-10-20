<?php

declare(strict_types=1);

namespace Zef\Test\Exception;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Exception\ZefException;
use Zef\Test\Support\Exception\ExplanatoryException;
use Zef\Test\Support\Exception\Ü9ProbeException;
use Zef\Test\Support\Excëption\UnicodeNamespaceException;

/**
 * The typed-exception root's own constructor contracts: the stable error
 * code grammar, the trace id grammar, and the default error code derived
 * from the class identity -- including the multibyte and length corners
 * where the derivation's arithmetic is observable. The fixtures the
 * derivation probes live in the test tree, outside the coverage source
 * scope, so only the root itself is declared a coverage target.
 *
 * @internal
 */
#[CoversClass(ZefException::class)]
final class ZefExceptionTest extends TestCase
{
    public function testTheRootDefaultsItsCodeAndDerivesItsErrorCode(): void
    {
        $explanatoryException = new ExplanatoryException('Failed safely.');

        self::assertSame(0, $explanatoryException->getCode());
        self::assertSame('E', $explanatoryException->getErrorCode());
        self::assertNull($explanatoryException->getTraceId());
        self::assertSame([], $explanatoryException->getDetails());
    }

    public function testTheErrorCodeMustBeACompleteUppercaseToken(): void
    {
        new ExplanatoryException('valid', 0, null, 'AB_CD9');

        $this->expectException(InvalidArgumentException::class);

        new ExplanatoryException('leading lowercase byte', 0, null, 'xABC');
    }

    public function testTheErrorCodeMustEndAtTheLastByte(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExplanatoryException('trailing lowercase byte', 0, null, 'AB_CDx');
    }

    public function testTheErrorCodeMustEndAtTheLastByteWithoutANewline(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExplanatoryException('trailing newline', 0, null, "AB_CD\n");
    }

    public function testTheTraceIdMustEndAtTheLastByteWithoutANewline(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExplanatoryException('trailing newline', 0, null, 'VALID_CODE', [], "req-123\n");
    }

    public function testTheDefaultErrorCodeDerivesFromTheClassIdentity(): void
    {
        self::assertSame('U_N', new UnicodeNamespaceException()->getErrorCode());
        self::assertSame('FRAMEWORK_ERROR', new Ü9ProbeException()->getErrorCode());
    }

    public function testTheDefaultErrorCodeTruncatesAtSixtyFourCharacters(): void
    {
        $overlong = 'U' . str_repeat('1', 64) . 'Exception';

        $overlongException = new ('Zef\Test\Support\Excëptïon2\\' . $overlong)();

        self::assertSame('U' . str_repeat('1', 63), $overlongException->getErrorCode());
    }
}
