<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Redis;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Redis\RespCommandEncoder;

/**
 * Pins the RESP request grammar's writing half: the multibulk
 * spelling of one command, the length prefix that keeps a payload
 * binary-safe, the integer argument's decimal cast at the single
 * boundary, and the empty command the grammar itself refuses to
 * send.
 *
 * @internal
 */
#[CoversClass(RespCommandEncoder::class)]
final class RespCommandEncoderTest extends TestCase
{
    public function testACommandEncodesAsItsMultibulkBytes(): void
    {
        self::assertSame(
            "*4\r\n$13\r\nZRANGEBYSCORE\r\n$7\r\nzef:bus\r\n$4\r\n-inf\r\n$3\r\n100\r\n",
            new RespCommandEncoder()->encode(
                ['ZRANGEBYSCORE', 'zef:bus', '-inf', '100'],
            ),
        );
    }

    public function testAnEmptyCommandIsRefused(): void
    {
        try {
            new RespCommandEncoder()->encode([]);
            self::fail('The empty command was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A Redis command must carry at least its own verb.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnIntegerArgumentEncodesAsItsDecimalSpelling(): void
    {
        self::assertSame(
            "*3\r\n$4\r\nZADD\r\n$7\r\nzef:bus\r\n$3\r\n100\r\n",
            new RespCommandEncoder()->encode(['ZADD', 'zef:bus', 100]),
        );
    }

    public function testAPayloadCarryingTheProtocolsOwnLineEndingStaysWhole(): void
    {
        self::assertSame(
            "*2\r\n$3\r\nSET\r\n$4\r\na\r\nb\r\n",
            new RespCommandEncoder()->encode(['SET', "a\r\nb"]),
        );
    }

    public function testAMultibytePayloadIsMeasuredInBytes(): void
    {
        self::assertSame(
            "*2\r\n$3\r\nSET\r\n$6\r\nÄrger\r\n",
            new RespCommandEncoder()->encode(['SET', 'Ärger']),
        );
    }

    public function testAnEmptyArgumentEncodesAsItsZeroLengthBulk(): void
    {
        self::assertSame(
            "*2\r\n$3\r\nSET\r\n$0\r\n\r\n",
            new RespCommandEncoder()->encode(['SET', '']),
        );
    }
}
