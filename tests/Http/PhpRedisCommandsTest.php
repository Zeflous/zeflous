<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\PhpRedisCommands;
use Zef\Framework\Http\PhpRedisConnection;
use Zef\Test\Support\Http\RedisDouble;

/**
 * The phpredis extension boundary, verified completely and
 * environment-agnostically: the connection half's every failure arm
 * (the refused connection, the throwing connection) and the command
 * half's every delegation are exercised against the real class double
 * ({@see RedisDouble}) -- an implementor of the family's own connection
 * seam, not a subclass of the extension's class, so the double's
 * behaviour is the test's own in every environment and the analysers
 * read its shape straight off the class. The production path's own
 * `\Redis` enters only through the union's extension arm, engine-checked
 * wherever the runner carries it, and fails its own honest way (the
 * construction seam's suite verifies that refusal).
 *
 * @internal
 */
#[CoversClass(PhpRedisCommands::class)]
#[CoversClass(PhpRedisConnection::class)]
final class PhpRedisCommandsTest extends TestCase
{
    public function testARefusedConnectionFailsClosedWithTheEndpointNamed(): void
    {
        $redisDouble = new RedisDouble();
        $redisDouble->connectResult = false;

        try {
            new PhpRedisCommands('redis.example.com', 6380, $redisDouble);
            self::fail('A refused connection must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The redis rate-limit store is unavailable at redis.example.com:6380'
                . ' (install ext-redis and start the server, or declare the memory store).',
                $httpException->getMessage(),
            );
        }
    }

    public function testAThrowingConnectionFailsClosedWithTheEndpointNamed(): void
    {
        $redisDouble = new RedisDouble();
        $redisDouble->connectThrows = true;

        try {
            new PhpRedisCommands('redis.example.com', 6380, $redisDouble);
            self::fail('A throwing connection must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The redis rate-limit store is unavailable at redis.example.com:6380'
                . ' (install ext-redis and start the server, or declare the memory store).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheCommandDelegationsReachTheRedisInstance(): void
    {
        $redisDouble = new RedisDouble();
        $phpRedisCommands = new PhpRedisCommands('redis.example.com', 6380, $redisDouble);

        self::assertSame(1, $phpRedisCommands->incr('zef:ratelimit:abc'));
        self::assertSame(60, $phpRedisCommands->ttl('zef:ratelimit:abc'));
        $phpRedisCommands->expire('zef:ratelimit:abc', 60);

        self::assertSame(
            [
                ['connect', 'redis.example.com', 6380],
                ['incr', 'zef:ratelimit:abc'],
                ['ttl', 'zef:ratelimit:abc'],
                ['expire', 'zef:ratelimit:abc', 60],
            ],
            $redisDouble->calls,
        );
    }
}
