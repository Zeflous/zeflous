<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Redis;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\PhpRedisCommands;

/**
 * The phpredis extension boundary, verified completely: the real `\Redis`
 * construction, connection and command delegation run inside a child
 * process against an eval-defined class double (the test environment
 * carries no phpredis extension, and the double gives the connection's
 * fail-closed refusal and every delegation a deterministic answer), so
 * the boundary carries its own coverage and its mutants are killed
 * without a single ignore entry.
 *
 * The double records every call into `$GLOBALS` and answers the
 * connection from the `$connectResult` the test configures, which is the
 * contract the boundary needs: one construction, one connection attempt,
 * and three one-line delegations.
 *
 * @internal
 */
#[CoversClass(PhpRedisCommands::class)]
final class PhpRedisCommandsTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testARefusedConnectionFailsClosedWithTheEndpointNamed(): void
    {
        $this->defineRedisDouble();
        Redis::$connectResult = false;

        try {
            new PhpRedisCommands('redis.example.com', 6380);
            self::fail('A refused connection must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The redis rate-limit store could not connect to redis.example.com:6380.',
                $httpException->getMessage(),
            );
        }
    }

    #[RunInSeparateProcess]
    public function testTheCommandDelegationsReachTheRedisInstance(): void
    {
        $this->defineRedisDouble();
        Redis::$connectResult = true;

        $phpRedisCommands = new PhpRedisCommands('redis.example.com', 6380);

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
            $GLOBALS['__redis_calls'],
        );
    }

    /**
     * Defines the \Redis class double in this child process: absent from
     * the environment, so the definition is the test's own; it records
     * every call into `$GLOBALS` and answers the connection from the
     * static the test configures.
     */
    private function defineRedisDouble(): void
    {
        $doubles = <<<'PHP'
            namespace {
                if (!class_exists('Redis', false)) {
                    class Redis
                    {
                        public static bool $connectResult = true;

                        public function __construct()
                        {
                            $GLOBALS['__redis_calls'] = [];
                        }

                        public function connect(string $host, int $port = 6379, float $timeout = 0.0): bool
                        {
                            $GLOBALS['__redis_calls'][] = ['connect', $host, $port];

                            return static::$connectResult;
                        }

                        public function incr(string $key): int|string
                        {
                            $GLOBALS['__redis_calls'][] = ['incr', $key];

                            return 1;
                        }

                        public function ttl(string $key): int|false
                        {
                            $GLOBALS['__redis_calls'][] = ['ttl', $key];

                            return 60;
                        }

                        public function expire(string $key, int $seconds): void
                        {
                            $GLOBALS['__redis_calls'][] = ['expire', $key, $seconds];
                        }
                    }
                }
            }
            PHP;
        eval($doubles);
    }
}
