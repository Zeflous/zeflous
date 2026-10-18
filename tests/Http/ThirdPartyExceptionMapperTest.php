<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Exception\InternalException;
use Zef\Framework\Http\ThirdPartyExceptionMapper;

#[CoversClass(ThirdPartyExceptionMapper::class)]
final class ThirdPartyExceptionMapperTest extends TestCase
{
    public function testPdoFailureIsNormalizedWithoutExposingDriverMessage(): void
    {
        $previous = new PDOException('SQL error with private query data');
        $previous->errorInfo = ['23000', 1062, 'private driver details'];

        $mapped = ThirdPartyExceptionMapper::normalize($previous);

        self::assertInstanceOf(InternalException::class, $mapped);
        self::assertSame('A database operation failed.', $mapped->getMessage());
        self::assertSame('DATABASE_ERROR', $mapped->getErrorCode());
        self::assertSame(['sql_state' => '23000'], $mapped->getDetails());
        self::assertSame($previous, $mapped->getPrevious());
        self::assertStringNotContainsString('private query data', $mapped->getMessage());
    }

    public function testUnknownThrowableIsPreserved(): void
    {
        $exception = new RuntimeException('application fault');

        self::assertSame($exception, ThirdPartyExceptionMapper::normalize($exception));
    }
}
