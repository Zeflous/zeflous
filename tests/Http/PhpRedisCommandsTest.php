<?php

declare(strict_types=1);

namespace Zef\Test;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Redis;
use RuntimeException;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\PhpRedisCommands;
use Zef\Framework\Http\PhpRedisConnection;

/**
 * The phpredis extension boundary, verified completely and
 * environment-agnostically: one anonymous subclass of the real `\Redis`
 * injected through the constructor's connection argument -- where the
 * runner carries the extension the subclass overrides the connection
 * and the three commands with the signatures phpredis 6 declares
 * (verified identical across the 6.1.0 through 6.3.0 releases), and
 * where it does not a bare eval-defined shell gives the subclass the
 * same parent to extend. Either way every delegation, the refused
 * connection and the throwing connection are the double's own answers,
 * the analysers read its shape straight off the class, and the boundary
 * carries its own coverage with its mutants killed -- no ignore entry.
 *
 * Each test runs in its own process so the shell definition never
 * leaks into another test's environment view (a process that carried
 * the shell would let the production construction find a `\Redis` it
 * must not find).
 *
 * @internal
 */
#[CoversClass(PhpRedisCommands::class)]
#[CoversClass(PhpRedisConnection::class)]
final class PhpRedisCommandsTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testARefusedConnectionFailsClosedWithTheEndpointNamed(): void
    {
        $redis = $this->redisDouble();
        $redis->connectResult = false;

        try {
            new PhpRedisCommands('redis.example.com', 6380, $redis);
            self::fail('A refused connection must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The redis rate-limit store is unavailable at redis.example.com:6380'
                . ' (install ext-redis and start the server, or declare the memory store).',
                $httpException->getMessage(),
            );
        }
    }

    #[RunInSeparateProcess]
    public function testAThrowingConnectionFailsClosedWithTheEndpointNamed(): void
    {
        $redis = $this->redisDouble();
        $redis->connectThrows = true;

        try {
            new PhpRedisCommands('redis.example.com', 6380, $redis);
            self::fail('A throwing connection must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The redis rate-limit store is unavailable at redis.example.com:6380'
                . ' (install ext-redis and start the server, or declare the memory store).',
                $httpException->getMessage(),
            );
        }
    }

    #[RunInSeparateProcess]
    public function testTheCommandDelegationsReachTheRedisInstance(): void
    {
        $redis = $this->redisDouble();
        $phpRedisCommands = new PhpRedisCommands('redis.example.com', 6380, $redis);

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
            $redis->calls,
        );
    }

    /**
     * The environment-appropriate double: an anonymous subclass of the
     * real `\Redis`. Where the extension is present the subclass's
     * overrides carry exactly the signatures phpredis 6 declares; where
     * it is absent an eval-defined shell -- one class declaration with
     * the same four methods -- gives the subclass the same parent to
     * extend, so the double's behaviour is the test's own in every
     * environment. The parameter names follow the stub's, the shapes
     * follow the extension's, and the return types are the narrows the
     * engine accepts against both.
     */
    private function redisDouble(): Redis
    {
        if (!class_exists('Redis', false)) {
            eval(<<<'PHP'
                class Redis
                {
                    public function connect(
                        string $host,
                        int $port = 6379,
                        float $timeout = 0.0,
                        ?string $persistent_id = null,
                        int $retry_interval = 0,
                        float $read_timeout = 0.0,
                        ?array $context = null,
                    ): bool {
                        return false;
                    }

                    public function incr(string $key, int $by = 1): int
                    {
                        return 0;
                    }

                    public function ttl(string $key): int
                    {
                        return 0;
                    }

                    public function expire(string $key, int $seconds, ?string $mode = null): bool
                    {
                        return false;
                    }
                }
                PHP);
        }

        return new class extends Redis {
            public bool $connectResult = true;

            public bool $connectThrows = false;

            /** @var list<array<int, int|string>> */
            public array $calls = [];

            /**
             * @param null|array<string, mixed> $context
             */
            #[Override]
            public function connect(
                string $host,
                int $port = 6379,
                float $timeout = 0.0,
                ?string $persistent_id = null,
                int $retry_interval = 0,
                float $read_timeout = 0.0,
                ?array $context = null,
            ): bool {
                $this->calls[] = ['connect', $host, $port];

                if ($this->connectThrows) {
                    throw new RuntimeException('the connection attempt threw');
                }

                return $this->connectResult;
            }

            #[Override]
            public function incr(string $key, int $by = 1): int
            {
                $this->calls[] = ['incr', $key];

                return 1;
            }

            #[Override]
            public function ttl(string $key): int
            {
                $this->calls[] = ['ttl', $key];

                return 60;
            }

            #[Override]
            public function expire(string $key, int $seconds, ?string $mode = null): bool
            {
                $this->calls[] = ['expire', $key, $seconds];

                return true;
            }
        };
    }
}
