<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Redis;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\SecurityFamilies;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\RateLimitFactory;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The rate-limit construction seam, on its own terms: the memory store's
 * default build, the fail-closed store selection (an unavailable
 * extension halts the boot with the remediation spelled out), the
 * unknown-family halt through the shared census, and the two shared
 * stores' happy paths -- built and exercised inside child processes
 * whose eval-defined doubles stand in for the absent extensions, so the
 * apcu and redis arms answer real accounting.
 *
 * @internal
 */
#[CoversClass(RateLimitFactory::class)]
#[CoversClass(SecurityFamilies::class)]
final class RateLimitFactoryTest extends TestCase
{
    public function testTheFactoryBuildsTheMemoryStoreFromTheFrozenSubtree(): void
    {
        $config = new Config([
            'security' => [
                'rate_limit' => [
                    'enabled' => true,
                    'limit' => 2,
                    'window' => 3600,
                ],
            ],
        ]);

        $middleware = RateLimitFactory::fromConfig($config);

        $response = $middleware
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame('2', $response->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('1', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testTheFactoryReadsTheDefaultsWhenTheFamilyIsAbsent(): void
    {
        $middleware = RateLimitFactory::fromConfig(new Config());

        $response = $middleware
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertFalse($response->hasHeader('X-RateLimit-Limit'), 'the default posture is disarmed');
    }

    public function testTheFactoryHaltsOnAnUnknownFamily(): void
    {
        $config = new Config(['security' => ['rate_limitt' => ['enabled' => true]]]);

        try {
            RateLimitFactory::fromConfig($config);
            self::fail('An unknown family must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security configuration carries the unknown family 'rate_limitt'"
                . ' (known families: headers, rate_limit).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFactoryHaltsWhenTheApcuExtensionIsAbsent(): void
    {
        $config = new Config(['security' => ['rate_limit' => ['store' => 'apcu']]]);

        try {
            RateLimitFactory::fromConfig($config);
            self::fail('A declared apcu store without the extension must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit store apcu requires the APCu extension'
                . ' (install ext-apcu, or declare the memory store).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFactoryHaltsWhenThePhpRedisExtensionIsAbsent(): void
    {
        $config = new Config(['security' => ['rate_limit' => ['store' => 'redis']]]);

        try {
            RateLimitFactory::fromConfig($config);
            self::fail('A declared redis store without the extension must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit store redis requires the phpredis extension'
                . ' (install ext-redis, or declare the memory store).',
                $httpException->getMessage(),
            );
        }
    }

    #[RunInSeparateProcess]
    public function testTheApcuStoreBuildsAndAccountsThroughTheFunctionDoubles(): void
    {
        $this->defineApcuDoubles();

        $config = new Config([
            'security' => [
                'rate_limit' => [
                    'enabled' => true,
                    'limit' => 5,
                    'window' => 60,
                    'store' => 'apcu',
                ],
            ],
        ]);

        $response = RateLimitFactory::fromConfig($config)
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('4', $response->getHeaderLine('X-RateLimit-Remaining'));

        $incCalls = $GLOBALS['__apcu_inc_calls'] ?? null;
        self::assertIsArray($incCalls);
        self::assertNotCount(0, $incCalls, 'the accounting reached the apcu boundary');
    }

    #[RunInSeparateProcess]
    public function testTheRedisStoreBuildsAndAccountsThroughTheClassDouble(): void
    {
        $this->defineRedisDouble();

        $config = new Config([
            'security' => [
                'rate_limit' => [
                    'enabled' => true,
                    'limit' => 5,
                    'window' => 60,
                    'store' => 'redis',
                    'redis_host' => 'redis.internal',
                    'redis_port' => 6380,
                ],
            ],
        ]);

        $response = RateLimitFactory::fromConfig($config)
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('4', $response->getHeaderLine('X-RateLimit-Remaining'));
        self::assertSame(
            [
                ['connect', 'redis.internal', 6380],
                ['incr', 'zef:ratelimit:' . hash('sha256', '0.0.0.0')],
                ['ttl', 'zef:ratelimit:' . hash('sha256', '0.0.0.0')],
                ['expire', 'zef:ratelimit:' . hash('sha256', '0.0.0.0'), 60],
            ],
            $GLOBALS['__redis_calls'],
            'the endpoint the knobs declared is the endpoint the boundary connected to',
        );
    }

    #[RunInSeparateProcess]
    public function testTheRedisStoreHaltsWhenTheConnectionIsRefused(): void
    {
        $this->defineRedisDouble();
        Redis::$connectResult = false;

        $config = new Config([
            'security' => [
                'rate_limit' => [
                    'store' => 'redis',
                    'redis_host' => 'redis.internal',
                    'redis_port' => 6380,
                ],
            ],
        ]);

        try {
            RateLimitFactory::fromConfig($config);
            self::fail('A refused connection must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The redis rate-limit store could not connect to redis.internal:6380.',
                $httpException->getMessage(),
            );
        }
    }

    private function request(): ServerRequest
    {
        return new ServerRequest('GET', new Uri('https://app.example.com/health'));
    }

    /**
     * Defines the apcu function doubles in this child process (absent from
     * the environment, so the definition is the test's own): an increment
     * that counts in `$GLOBALS`, an add that succeeds once per key, and a
     * fetch that reads what was added.
     */
    private function defineApcuDoubles(): void
    {
        $doubles = <<<'PHP'
            namespace {
                if (!function_exists('apcu_inc')) {
                    function apcu_inc(string $key, int $step = 1, ?bool &$success = null, int $ttl = 0): int|false
                    {
                        $GLOBALS['__apcu_inc_calls'][] = [$key, $step, $ttl];
                        $success = true;
                        $counters = $GLOBALS['__apcu_counters'] ?? [];
                        $counters[$key] = ($counters[$key] ?? 0) + $step;
                        $GLOBALS['__apcu_counters'] = $counters;

                        return $counters[$key];
                    }
                }

                if (!function_exists('apcu_add')) {
                    function apcu_add(string $key, mixed $value, int $ttl = 0): bool
                    {
                        $entries = $GLOBALS['__apcu_entries'] ?? [];

                        if (\array_key_exists($key, $entries)) {
                            return false;
                        }

                        $entries[$key] = $value;
                        $GLOBALS['__apcu_entries'] = $entries;

                        return true;
                    }
                }

                if (!function_exists('apcu_fetch')) {
                    function apcu_fetch(string $key, ?bool &$success = null): mixed
                    {
                        $entries = $GLOBALS['__apcu_entries'] ?? [];

                        if (\array_key_exists($key, $entries)) {
                            $success = true;

                            return $entries[$key];
                        }

                        return false;
                    }
                }
            }
            PHP;
        eval($doubles);

        $GLOBALS['__apcu_inc_calls'] = [];
        $GLOBALS['__apcu_counters'] = [];
        $GLOBALS['__apcu_entries'] = [];
    }

    /**
     * Defines the \Redis class double in this child process (absent from
     * the environment, so the definition is the test's own): it records
     * every call into `$GLOBALS` and answers a live first bucket.
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

                            return -2;
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
