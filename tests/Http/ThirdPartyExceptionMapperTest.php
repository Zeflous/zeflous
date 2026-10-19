<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
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
}
