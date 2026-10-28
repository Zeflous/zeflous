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
use Zef\Test\Support\Adapters\Redis\RedisMockServer;
use Zef\Test\Support\Adapters\Redis\RespSocketDoubleState;

/**
 * Pins the wire against a real socket: the mock server's own RESP
 * grammar answered over a real loopback connection -- every command
 * the delivery store speaks carried and answered for real, the
 * sorted set's own (score, member) order, the claim's NX and PX
 * semantics against a second connection, the shared state two
 * connections observe, and the refused connection naming the
 * address the engine refused. The socket boundary's doubles stay
 * passthrough here -- this tier's whole point is the bytes a real
 * stream carries.
 *
 * @internal
 */
#[CoversClass(RespBulkReply::class)]
#[CoversClass(RespCommandEncoder::class)]
#[CoversClass(RespConnection::class)]
#[CoversClass(RespReader::class)]
#[CoversClass(RespReplyDecoder::class)]
#[CoversClass(RespSocket::class)]
final class RespConnectionServerTest extends TestCase
{
    private static RedisMockServer $redisMockServer;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        self::$redisMockServer = new RedisMockServer();
        self::$redisMockServer->start();
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        self::$redisMockServer->stop();
    }

    #[Override]
    protected function setUp(): void
    {
        RespSocketDoubleState::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        RespSocketDoubleState::reset();
    }

    public function testTheProtocolsHealthVerbRoundTrips(): void
    {
        self::assertSame('PONG', $this->connection()->command('PING'));
    }

    public function testEveryCommandTheStoreSpeaksRoundTrips(): void
    {
        $connection = $this->connection();

        self::assertSame('OK', $connection->command('SET', 'k1', 'v1'));
        self::assertSame('v1', $connection->command('GET', 'k1'));
        self::assertNull($connection->command('GET', 'absent'));
        self::assertSame(1, $connection->command('ZADD', 'z1', 100, 'e2'));
        self::assertSame(1, $connection->command('ZADD', 'z1', 50, 'e1'));
        self::assertSame(1, $connection->command('ZADD', 'z1', 100, 'e3'));
        self::assertSame(
            ['e1', 'e2', 'e3'],
            $connection->command('ZRANGEBYSCORE', 'z1', '-inf', 100),
        );
        self::assertSame(['e1'], $connection->command('ZRANGEBYSCORE', 'z1', '-inf', 50));
        self::assertSame([], $connection->command('ZRANGEBYSCORE', 'z1', '-inf', 49));
        self::assertSame(3, $connection->command('ZCARD', 'z1'));
        self::assertSame(1, $connection->command('ZREM', 'z1', 'e1'));
        self::assertSame(2, $connection->command('ZCARD', 'z1'));
        self::assertSame(
            ['v1', null],
            $connection->command('MGET', 'k1', 'absent'),
        );
        self::assertSame(1, $connection->command('DEL', 'k1', 'absent'));
        self::assertNull($connection->command('GET', 'k1'));
    }

    public function testAPayloadCarryingTheProtocolsOwnLineEndingRoundTrips(): void
    {
        $connection = $this->connection();

        self::assertSame(
            'OK',
            $connection->command('SET', 'binary', "a\r\nb"),
        );
        self::assertSame("a\r\nb", $connection->command('GET', 'binary'));
    }

    public function testASecondConnectionObservesTheSharedState(): void
    {
        $this->connection()->command('SET', 'shared', 'visible');

        self::assertSame(
            'visible',
            $this->connection()->command('GET', 'shared'),
        );
    }

    public function testAClaimIsExclusiveBetweenConnectionsAndExpires(): void
    {
        $first = $this->connection();
        $second = $this->connection();

        self::assertSame(
            'OK',
            $first->command('SET', 'claim', 'token-a', 'NX', 'PX', 200),
        );
        self::assertNull(
            $second->command('SET', 'claim', 'token-b', 'NX', 'PX', 200),
        );
        self::assertSame(
            'token-a',
            $second->command('GET', 'claim'),
        );

        usleep(300_000);

        self::assertSame(
            'OK',
            $second->command('SET', 'claim', 'token-b', 'NX', 'PX', 200),
        );
        self::assertSame(
            'token-b',
            $first->command('GET', 'claim'),
        );
    }

    public function testTheServersOwnErrorReplyIsCarriedOutOverTheWire(): void
    {
        try {
            $this->connection()->command('NOSUCH', 'x');
            self::fail('The unknown command was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis server refused the command: ERR unknown command 'NOSUCH'",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARefusedConnectionNamesTheAddressOverTheRealWire(): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorString);
        $address = $listener === false
            ? null
            : stream_socket_get_name($listener, false);

        if (!\is_string($address) || $address === '') {
            self::fail('The probe listener did not answer its address.');
        }

        $port = (int) mb_substr($address, (int) mb_strrpos($address, ':') + 1, null, '8bit');
        fclose($listener);

        try {
            new RespConnection('127.0.0.1', $port, 0.5);
            self::fail('The refused connection was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringStartsWith(
                "The Redis server at '127.0.0.1:{$port}' refused the connection:",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    private function connection(): RespConnection
    {
        return new RespConnection(self::$redisMockServer->host(), self::$redisMockServer->port(), 2.0);
    }
}
