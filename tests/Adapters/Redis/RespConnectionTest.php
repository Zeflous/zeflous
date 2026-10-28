<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Redis;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Redis\RespBulkReply;
use Zef\Framework\Adapters\Redis\RespCommandEncoder;
use Zef\Framework\Adapters\Redis\RespConnection;
use Zef\Framework\Adapters\Redis\RespReader;
use Zef\Framework\Adapters\Redis\RespReplyDecoder;
use Zef\Framework\Adapters\Redis\RespSocket;
use Zef\Test\Support\Adapters\Redis\RespSocketDoubleState;

/**
 * Pins the wire over the socket boundary's function doubles: every
 * reply type the grammar answers, the buffered reassembly of a
 * reply the engine delivers in pieces, the write loop that keeps
 * writing past a partial write, and every refusal arm -- the
 * refused connection with its address named, the refused write, the
 * dead stream, the end-of-stream mid-reply, the corrupt type byte,
 * the non-numeric length, and the server's own error reply carried
 * out with its message intact. The commands the connection writes
 * are recorded and asserted as the wire's own observable.
 *
 * @internal
 */
#[CoversClass(RespBulkReply::class)]
#[CoversClass(RespCommandEncoder::class)]
#[CoversClass(RespConnection::class)]
#[CoversClass(RespReader::class)]
#[CoversClass(RespReplyDecoder::class)]
#[CoversClass(RespSocket::class)]
final class RespConnectionTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        RespSocketDoubleState::reset();
        RespSocketDoubleState::$connectAnswer = fopen('php://memory', 'r+b');
        RespSocketDoubleState::$readChunks = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        RespSocketDoubleState::reset();
    }

    public function testACommandIsCarriedAndItsSimpleStringAnswered(): void
    {
        RespSocketDoubleState::$readChunks = ["+PONG\r\n"];

        self::assertSame('PONG', $this->connection()->command('PING'));

        self::assertSame(
            ["*1\r\n$4\r\nPING\r\n"],
            RespSocketDoubleState::$writtenCommands,
        );
    }

    public function testAReplyDeliveredInPiecesIsReassembledWhole(): void
    {
        RespSocketDoubleState::$readChunks = ['+PO', 'NG', "\r\n"];

        self::assertSame('PONG', $this->connection()->command('PING'));
    }

    public function testAnEmptySimpleStringIsTheEmptyString(): void
    {
        RespSocketDoubleState::$readChunks = ["+\r\n"];

        self::assertSame('', $this->connection()->command('STATUS'));
    }

    public function testAnIntegerReplyIsAnsweredAsItsInteger(): void
    {
        RespSocketDoubleState::$readChunks = [":42\r\n"];

        self::assertSame(42, $this->connection()->command('ZCARD', 'zef:bus'));
    }

    public function testANegativeIntegerReplyIsAnsweredAsItsInteger(): void
    {
        RespSocketDoubleState::$readChunks = [":-3\r\n"];

        self::assertSame(-3, $this->connection()->command('ZCARD', 'zef:bus'));
    }

    public function testABulkReplyIsAnsweredAsItsString(): void
    {
        RespSocketDoubleState::$readChunks = ["$6\r\nrecord\r\n"];

        self::assertSame('record', $this->connection()->command('GET', 'k'));
    }

    public function testABulkPayloadDeliveredInPiecesIsReassembledWhole(): void
    {
        RespSocketDoubleState::$readChunks = ["$6\r\nre", 'co', "rd\r\n"];

        self::assertSame('record', $this->connection()->command('GET', 'k'));
    }

    public function testABulkPayloadCarryingTheProtocolsOwnLineEndingStaysWhole(): void
    {
        RespSocketDoubleState::$readChunks = ["$4\r\na\r\nb\r\n"];

        self::assertSame("a\r\nb", $this->connection()->command('GET', 'k'));
    }

    public function testAnEmptyBulkReplyIsTheEmptyString(): void
    {
        RespSocketDoubleState::$readChunks = ["$0\r\n\r\n"];

        self::assertSame('', $this->connection()->command('GET', 'k'));
    }

    public function testTheNullBulkIsAnsweredAsNull(): void
    {
        RespSocketDoubleState::$readChunks = ["\$-1\r\n"];

        self::assertNull($this->connection()->command('GET', 'k'));
    }

    public function testAnArrayReplyIsAnsweredAsItsDecodedElements(): void
    {
        RespSocketDoubleState::$readChunks = ["*3\r\n$2\r\ne1\r\n\$-1\r\n$2\r\ne2\r\n"];

        self::assertSame(
            ['e1', null, 'e2'],
            $this->connection()->command('MGET', 'k1', 'k2', 'k3'),
        );
    }

    public function testAnEmptyArrayReplyIsTheEmptyList(): void
    {
        RespSocketDoubleState::$readChunks = ["*0\r\n"];

        self::assertSame([], $this->connection()->command('ZRANGEBYSCORE', 'k', '-inf', '1'));
    }

    public function testTheNullArrayIsAnsweredAsNull(): void
    {
        RespSocketDoubleState::$readChunks = ["*-1\r\n"];

        self::assertNull($this->connection()->command('ZRANGEBYSCORE', 'k', '-inf', '1'));
    }

    public function testANestedArrayReplyDecodesRecursively(): void
    {
        RespSocketDoubleState::$readChunks = ["*2\r\n:1\r\n*2\r\n:2\r\n$1\r\nx\r\n"];

        self::assertSame(
            [1, [2, 'x']],
            $this->connection()->command('NESTED'),
        );
    }

    public function testAPartialWriteIsWrittenPastUntilWhole(): void
    {
        RespSocketDoubleState::$readChunks = ["+OK\r\n"];
        RespSocketDoubleState::$writeAnswer = 5;

        self::assertSame('OK', $this->connection()->command('SET', 'k', 'v'));

        self::assertGreaterThan(
            1,
            \count(RespSocketDoubleState::$writtenCommands),
            'The partial write was answered by one whole write.',
        );
        self::assertSame(
            "*3\r\n$3\r\nSET\r\n$1\r\nk\r\n$1\r\nv\r\n",
            implode('', RespSocketDoubleState::$writtenCommands),
        );
    }

    public function testTheReadDeadlineIsRefinedOnTheStreamItCarries(): void
    {
        $this->connection('127.0.0.1', 6379, 2.5);

        self::assertSame([[2, 500_000]], RespSocketDoubleState::$setTimeoutCalls);
    }

    public function testARefusedConnectionNamesTheAddressAndItsCause(): void
    {
        RespSocketDoubleState::$connectAnswer = false;
        RespSocketDoubleState::$connectErrorCode = 111;
        RespSocketDoubleState::$connectErrorString = 'Connection refused';

        try {
            $this->connection();
            self::fail('The refused connection was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis server at '127.0.0.1:6379' refused the connection: Connection refused (111).",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnEmptyHostnameIsRefused(): void
    {
        $this->expectRefusal(
            'A Redis connection must carry a non-empty hostname.',
            '',
        );
    }

    public function testAnOutOfRangePortIsRefused(): void
    {
        $this->expectRefusal(
            'A Redis connection port must be between 1 and 65535, got 65536.',
            '127.0.0.1',
            65536,
        );
    }

    public function testAZeroTimeoutIsRefused(): void
    {
        $this->expectRefusal(
            'A Redis connection timeout must be above zero, got 0.',
            '127.0.0.1',
            6379,
            0.0,
        );
    }

    public function testARefusedWriteIsRefused(): void
    {
        RespSocketDoubleState::$writeAnswer = false;

        try {
            $this->connection()->command('PING');
            self::fail('The refused write was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The Redis connection cannot write a command.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testADeadStreamIsRefused(): void
    {
        RespSocketDoubleState::$readChunks = [false];

        try {
            $this->connection()->command('PING');
            self::fail('The dead stream was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The Redis connection cannot read a reply.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAStreamThatEndsMidReplyIsRefused(): void
    {
        RespSocketDoubleState::$readChunks = ['+PO'];

        try {
            $this->connection()->command('PING');
            self::fail('The ended stream was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The Redis connection ended before the reply was complete.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAStreamThatEndsMidBulkIsRefused(): void
    {
        RespSocketDoubleState::$readChunks = ["$6\r\nreco"];

        try {
            $this->connection()->command('GET', 'k');
            self::fail('The ended stream was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The Redis connection ended before the reply was complete.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheServersOwnErrorReplyIsCarriedOutWithItsMessage(): void
    {
        RespSocketDoubleState::$readChunks = ["-ERR unknown command 'NOSUCH'\r\n"];

        try {
            $this->connection()->command('NOSUCH');
            self::fail('The error reply was not carried out.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis server refused the command: ERR unknown command 'NOSUCH'",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnknownLeadingByteIsRefused(): void
    {
        RespSocketDoubleState::$readChunks = ["?what\r\n"];

        try {
            $this->connection()->command('PING');
            self::fail('The unknown type byte was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply's leading byte '?' is not a RESP type.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnEmptyLeadingLineIsRefused(): void
    {
        RespSocketDoubleState::$readChunks = ["\r\n"];

        try {
            $this->connection()->command('PING');
            self::fail('The empty leading line was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply's leading line is empty -- not a reply the grammar admits.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANonNumericIntegerReplyIsRefused(): void
    {
        RespSocketDoubleState::$readChunks = [":four\r\n"];

        try {
            $this->connection()->command('ZCARD', 'k');
            self::fail('The non-numeric integer was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply of type ':' is not a decimal integer: 'four'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANonNumericBulkLengthIsRefused(): void
    {
        RespSocketDoubleState::$readChunks = ["\$six\r\n"];

        try {
            $this->connection()->command('GET', 'k');
            self::fail('The non-numeric bulk length was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply of type '$' is not a decimal integer: 'six'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANegativeBulkLengthBeyondTheNullSpellingIsRefused(): void
    {
        RespSocketDoubleState::$readChunks = ["\$-2\r\n"];

        try {
            $this->connection()->command('GET', 'k');
            self::fail('The negative bulk length was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis bulk reply's length '-2' is not a length the grammar admits.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testABulkReplyWithoutTheProtocolsTrailerIsRefused(): void
    {
        RespSocketDoubleState::$readChunks = ["$3\r\nabcXY"];

        try {
            $this->connection()->command('GET', 'k');
            self::fail('The corrupt trailer was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis bulk reply does not end with the protocol's own line ending.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANegativeArrayCountBeyondTheNullSpellingIsRefused(): void
    {
        RespSocketDoubleState::$readChunks = ["*-2\r\n"];

        try {
            $this->connection()->command('MGET', 'k');
            self::fail('The negative array count was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis array reply's count '-2' is not a count the grammar admits.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnEmptyCommandIsRefused(): void
    {
        try {
            $this->connection()->command();
            self::fail('The empty command was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A Redis command must carry at least its own verb.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    private function connection(
        string $hostname = '127.0.0.1',
        int $port = 6379,
        float $timeoutSeconds = 5.0,
    ): RespConnection {
        return new RespConnection($hostname, $port, $timeoutSeconds);
    }

    private function expectRefusal(
        string $message,
        string $hostname,
        int $port = 6379,
        float $timeoutSeconds = 5.0,
    ): void {
        try {
            $this->connection($hostname, $port, $timeoutSeconds);
            self::fail('The invalid connection was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame($message, $invalidArgumentException->getMessage());
        }
    }
}
