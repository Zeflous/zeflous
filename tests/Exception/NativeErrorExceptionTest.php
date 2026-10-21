<?php

declare(strict_types=1);

namespace Zef\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Exception\CarriesHttpStatus;
use Zef\Framework\Exception\NativeErrorException;

/**
 * Direct battery for the process boundary's conversion signal: the
 * diagnostic construction site's bounded details, the stable error code,
 * and the internal-fault posture the answer carries.
 *
 * @internal
 */
#[CoversClass(NativeErrorException::class)]
final class NativeErrorExceptionTest extends TestCase
{
    public function testTheDiagnosticConstructionCarriesTheBoundedFacts(): void
    {
        $nativeErrorException = NativeErrorException::diagnostic(
            'Uncaught division by zero',
            'critical',
            '/var/app/src/worker.php',
            42,
        );

        self::assertSame('Uncaught division by zero', $nativeErrorException->getMessage());
        self::assertSame('NATIVE_ERROR', $nativeErrorException->getErrorCode());
        self::assertSame('/var/app/src/worker.php', $nativeErrorException->getDetails()['file']);
        self::assertSame(42, $nativeErrorException->getDetails()['line']);
        self::assertSame('critical', $nativeErrorException->getDetails()['severity']);
        self::assertNull($nativeErrorException->getTraceId());
        self::assertInstanceOf(CarriesHttpStatus::class, $nativeErrorException);
        self::assertSame(500, $nativeErrorException->httpStatus());
    }

    public function testTheDiagnosticConstructionBoundsTheFileFact(): void
    {
        $nativeErrorException = NativeErrorException::diagnostic(
            'The worker observed an impossible state.',
            'error',
            str_repeat('a', 2049),
            1,
        );

        self::assertSame(str_repeat('a', 2048), $nativeErrorException->getDetails()['file']);
    }

    public function testTheDiagnosticConstructionBoundsAMultibyteFileFact(): void
    {
        $nativeErrorException = NativeErrorException::diagnostic(
            'The worker observed an impossible state.',
            'error',
            str_repeat('é', 2048),
            1,
        );

        self::assertSame(str_repeat('é', 2048), $nativeErrorException->getDetails()['file']);
    }

    public function testTheDefaultConstructionDerivesTheCollapsedSpelling(): void
    {
        $nativeErrorException = new NativeErrorException('A bare spelling stays constructible.');

        self::assertSame('N_E', $nativeErrorException->getErrorCode());
        self::assertSame([], $nativeErrorException->getDetails());
        self::assertSame(500, $nativeErrorException->httpStatus());
    }
}
