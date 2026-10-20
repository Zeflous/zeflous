<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Exception\InternalException;
use Zef\Framework\Http\ThirdPartyExceptionMapper;

/**
 * @internal
 */
#[CoversClass(ThirdPartyExceptionMapper::class)]
final class ThirdPartyExceptionMapperTest extends TestCase
{
    public function testPdoFailureIsNormalizedWithoutExposingDriverMessage(): void
    {
        $pdoException = new PDOException('SQL error with private query data');
        $pdoException->errorInfo = ['23000', 1062, 'private driver details'];

        $mapped = ThirdPartyExceptionMapper::normalize($pdoException);

        self::assertInstanceOf(InternalException::class, $mapped);
        self::assertSame('A database operation failed.', $mapped->getMessage());
        self::assertSame(0, $mapped->getCode());
        self::assertSame('DATABASE_ERROR', $mapped->getErrorCode());
        self::assertSame(['sql_state' => '23000'], $mapped->getDetails());
        self::assertSame($pdoException, $mapped->getPrevious());
        self::assertStringNotContainsString('private query data', $mapped->getMessage());
    }

    public function testUnknownThrowableIsPreserved(): void
    {
        $runtimeException = new RuntimeException('application fault');

        self::assertSame($runtimeException, ThirdPartyExceptionMapper::normalize($runtimeException));
    }

    /**
     * @param list<mixed> $errorInfo the driver-reported error info whose first entry misses the anchored grammar
     */
    #[DataProvider('provideSqlStateCandidatesOutsideTheAnchoredShapeAreRejectedCases')]
    public function testSqlStateCandidatesOutsideTheAnchoredShapeAreRejected(array $errorInfo, string $candidate): void
    {
        $pdoException = new PDOException('db');
        $pdoException->errorInfo = $errorInfo;

        $mapped = ThirdPartyExceptionMapper::normalize($pdoException);

        self::assertInstanceOf(InternalException::class, $mapped);
        self::assertSame([], $mapped->getDetails(), 'The candidate ' . $candidate . ' must not surface as a SQLSTATE.');
    }

    /**
     * Every candidate below misses the anchored ^[0-9A-Z]{5}$D grammar for a
     * different reason: one byte too many with a trailing letter, garbage
     * ahead of a five-character tail, and a trailing newline the D flag
     * refuses at the dollar anchor.
     *
     * @return iterable<non-empty-string, array{list<mixed>, string}>
     */
    public static function provideSqlStateCandidatesOutsideTheAnchoredShapeAreRejectedCases(): iterable
    {
        yield 'six characters' => [['123456', 1062, 'x'], '123456'];

        yield 'garbage prefix' => [['x12345', 1062, 'x'], 'x12345'];

        yield 'trailing newline' => [["12345\n", 1062, 'x'], "12345\n"];
    }

    public function testALowercaseSqlStateIsAcceptedAndUppercased(): void
    {
        $pdoException = new PDOException('db');
        $pdoException->errorInfo = ['42s02', 1062, 'private driver details'];

        $mapped = ThirdPartyExceptionMapper::normalize($pdoException);

        self::assertInstanceOf(InternalException::class, $mapped);
        self::assertSame(['sql_state' => '42S02'], $mapped->getDetails());
    }

    public function testThePdoCodeFallbackWithoutErrorInfoYieldsNoSqlState(): void
    {
        $pdoException = new PDOException('db', 1045);

        $mapped = ThirdPartyExceptionMapper::normalize($pdoException);

        self::assertInstanceOf(InternalException::class, $mapped);
        self::assertSame([], $mapped->getDetails());
    }
}
