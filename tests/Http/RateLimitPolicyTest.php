<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\RateLimitBounds;
use Zef\Framework\Http\RateLimitKnobs;
use Zef\Framework\Http\RateLimitPolicy;
use Zef\Framework\Http\RateLimitStores;
use Zef\Framework\Http\StrictIntegers;
use Zef\Framework\Http\StrictToggles;
use Zef\Framework\Http\TrustedProxyKnobs;

/**
 * The rate-limit family's knob reading, on its own terms: the default
 * posture, the strict spellings (native and environment-string alike),
 * the closed store vocabulary, the proxy-list spellings, and the
 * fail-closed rejections -- plus the drift pin against the framework
 * profile's own declaration.
 *
 * @internal
 */
#[CoversClass(FrameworkDefaults::class)]
#[CoversClass(RateLimitBounds::class)]
#[CoversClass(RateLimitKnobs::class)]
#[CoversClass(RateLimitPolicy::class)]
#[CoversClass(RateLimitStores::class)]
#[CoversClass(StrictIntegers::class)]
#[CoversClass(StrictToggles::class)]
#[CoversClass(TrustedProxyKnobs::class)]
final class RateLimitPolicyTest extends TestCase
{
    public function testTheAbsentFamilyReadsTheDefaultPosture(): void
    {
        $policy = RateLimitPolicy::fromMap(null);

        self::assertFalse($policy->enabled);
        self::assertSame(100, $policy->limit);
        self::assertSame(60, $policy->window);
        self::assertSame(10000, $policy->maxKeys);
        self::assertSame(RateLimitStores::MEMORY, $policy->store);
        self::assertSame([], $policy->trustedProxies);
        self::assertSame('127.0.0.1', $policy->redisHost);
        self::assertSame(6379, $policy->redisPort);
    }

    public function testTheEmptyMapReadsTheDefaultPosture(): void
    {
        self::assertSame(
            $this->fieldMap(RateLimitPolicy::fromMap(null)),
            $this->fieldMap(RateLimitPolicy::fromMap([])),
        );
    }

    public function testTheNativeKnobsAreReadVerbatim(): void
    {
        $policy = RateLimitPolicy::fromMap([
            'enabled' => true,
            'limit' => 5,
            'window' => 300,
            'max_keys' => 128,
            'store' => 'redis',
            'trusted_proxies' => ['10.0.0.0/8', '127.0.0.1'],
            'redis_host' => 'redis.internal',
            'redis_port' => 6380,
        ]);

        self::assertTrue($policy->enabled);
        self::assertSame(5, $policy->limit);
        self::assertSame(300, $policy->window);
        self::assertSame(128, $policy->maxKeys);
        self::assertSame('redis', $policy->store);
        self::assertSame(['10.0.0.0/8', '127.0.0.1'], $policy->trustedProxies);
        self::assertSame('redis.internal', $policy->redisHost);
        self::assertSame(6380, $policy->redisPort);
    }

    public function testTheEnvironmentSpellingsFoldThroughTheStrictReadings(): void
    {
        $policy = RateLimitPolicy::fromMap([
            'enabled' => 'on',
            'limit' => '250',
            'window' => '120',
            'max_keys' => '2048',
            'store' => 'Redis',
            'trusted_proxies' => '10.0.0.0/8,, 127.0.0.1,',
            'redis_port' => '7000',
        ]);

        self::assertTrue($policy->enabled);
        self::assertSame(250, $policy->limit);
        self::assertSame(120, $policy->window);
        self::assertSame(2048, $policy->maxKeys);
        self::assertSame('redis', $policy->store, 'the store vocabulary folds case-insensitively');
        self::assertSame(['10.0.0.0/8', '127.0.0.1'], $policy->trustedProxies);
        self::assertSame(7000, $policy->redisPort);
    }

    public function testTheFrameworkProfileMirrorsThePolicyDefaults(): void
    {
        $fromEmptyMap = RateLimitPolicy::fromMap([]);
        $security = FrameworkDefaults::profile()['security'] ?? null;
        self::assertIsArray($security);
        $fromDefaults = $security['rate_limit'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'enabled' => $fromEmptyMap->enabled,
                'limit' => $fromEmptyMap->limit,
                'window' => $fromEmptyMap->window,
                'max_keys' => $fromEmptyMap->maxKeys,
                'store' => $fromEmptyMap->store,
                'trusted_proxies' => $fromEmptyMap->trustedProxies,
                'redis_host' => $fromEmptyMap->redisHost,
                'redis_port' => $fromEmptyMap->redisPort,
            ],
            $fromDefaults,
            'FrameworkDefaults::profile() security.rate_limit and the policy defaults must not drift apart.',
        );
    }

    public function testTheMinimalBoundariesAreValidKnobs(): void
    {
        $policy = RateLimitPolicy::fromMap([
            'limit' => 1,
            'window' => 1,
            'max_keys' => 1,
            'redis_port' => 65535,
        ]);

        self::assertSame(1, $policy->limit, 'a budget of one is a valid budget');
        self::assertSame(1, $policy->window, 'a one-second window is a valid window');
        self::assertSame(1, $policy->maxKeys, 'a capacity of one is a valid capacity');
        self::assertSame(65535, $policy->redisPort, 'the last port is a valid port');
    }

    public function testTheMinimalRedisPortIsAValidKnob(): void
    {
        $policy = RateLimitPolicy::fromMap(['redis_port' => 1]);

        self::assertSame(1, $policy->redisPort, 'the first port is a valid port');
    }

    public function testAnUnknownKnobAfterAKnownOneStillHalts(): void
    {
        try {
            RateLimitPolicy::fromMap(['enabled' => true, 'limitt' => 5]);
            self::fail('An unknown knob must halt the reading wherever it sits.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security.rate_limit configuration carries the unknown knob 'limitt'"
                . ' (known knobs: enabled, limit, window, max_keys, store, trusted_proxies,'
                . ' redis_host, redis_port).',
                $httpException->getMessage(),
            );
        }
    }

    public function testANonMapSubtreeHalts(): void
    {
        try {
            RateLimitPolicy::fromMap('nope');
            self::fail('A non-map subtree must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobHaltsWithTheKnownListSpelledOut(): void
    {
        try {
            RateLimitPolicy::fromMap(['limitt' => 5]);
            self::fail('An unknown knob must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security.rate_limit configuration carries the unknown knob 'limitt'"
                . ' (known knobs: enabled, limit, window, max_keys, store, trusted_proxies,'
                . ' redis_host, redis_port).',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnInvalidToggleHaltsWithTheKnobNamed(): void
    {
        try {
            RateLimitPolicy::fromMap(['enabled' => 'maybe']);
            self::fail('An invalid toggle must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit enabled knob must be a boolean or one of the boolean string '
                . 'spellings (true/false, on/off, yes/no, 1/0), string given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @dataProvider provideAnInvalidIntegerSpellingHaltsWithTheKnobNamedCases
     */
    #[DataProvider('provideAnInvalidIntegerSpellingHaltsWithTheKnobNamedCases')]
    public function testAnInvalidIntegerSpellingHaltsWithTheKnobNamed(
        string $knob,
        mixed $value,
        string $type,
    ): void {
        try {
            RateLimitPolicy::fromMap([$knob => $value]);
            self::fail('An invalid integer spelling must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security.rate_limit {$knob} knob must be an integer or an all-digit string, "
                . $type . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<array{string, mixed, string}>
     */
    public static function provideAnInvalidIntegerSpellingHaltsWithTheKnobNamedCases(): iterable
    {
        yield 'a word' => ['limit', 'many', 'string'];

        yield 'a float' => ['limit', 1.5, 'float'];

        yield 'a bool' => ['window', true, 'bool'];

        yield 'a negative sign' => ['limit', '-5', 'string'];

        yield 'a spaced number' => ['window', ' 60', 'string'];
    }

    /**
     * @dataProvider provideAnOutOfRangeLimitHaltsCases
     */
    #[DataProvider('provideAnOutOfRangeLimitHaltsCases')]
    public function testAnOutOfRangeLimitHalts(int $value): void
    {
        try {
            RateLimitPolicy::fromMap(['limit' => $value]);
            self::fail('An out-of-range limit must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit limit knob must be 1 or above.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<array{int}>
     */
    public static function provideAnOutOfRangeLimitHaltsCases(): iterable
    {
        yield 'a zero limit' => [0];

        yield 'a negative limit' => [-3];
    }

    public function testAnOutOfRangeWindowHalts(): void
    {
        try {
            RateLimitPolicy::fromMap(['window' => 0]);
            self::fail('An out-of-range window must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit window knob must be 1 or above.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnOutOfRangeMaxKeysHalts(): void
    {
        try {
            RateLimitPolicy::fromMap(['max_keys' => 0]);
            self::fail('An out-of-range capacity must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit max_keys knob must be 1 or above.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @dataProvider provideAnOutOfRangeRedisPortHaltsCases
     */
    #[DataProvider('provideAnOutOfRangeRedisPortHaltsCases')]
    public function testAnOutOfRangeRedisPortHalts(int $port): void
    {
        try {
            RateLimitPolicy::fromMap(['redis_port' => $port]);
            self::fail('An out-of-range port must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit redis_port knob must be between 1 and 65535.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<array{int}>
     */
    public static function provideAnOutOfRangeRedisPortHaltsCases(): iterable
    {
        yield 'port zero' => [0];

        yield 'port above 65535' => [65536];
    }

    public function testAnUnknownStoreHaltsWithTheValueSpelledOut(): void
    {
        try {
            RateLimitPolicy::fromMap(['store' => 'redsi']);
            self::fail('An unknown store must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit store knob must be one of the known stores '
                . "(memory, apcu, redis), 'redsi' given.",
                $httpException->getMessage(),
            );
        }
    }

    public function testANonStringStoreHalts(): void
    {
        try {
            RateLimitPolicy::fromMap(['store' => true]);
            self::fail('A non-string store must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit store knob must be one of the known stores '
                . '(memory, apcu, redis), bool given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testANonStringProxyEntryHalts(): void
    {
        try {
            RateLimitPolicy::fromMap(['trusted_proxies' => ['10.0.0.0/8', 42]]);
            self::fail('A non-string proxy entry must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit trusted_proxies knob must be a list of strings, '
                . 'int given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testANonListNonStringProxyValueHalts(): void
    {
        try {
            RateLimitPolicy::fromMap(['trusted_proxies' => 42]);
            self::fail('A proxy value that is neither list nor string must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit trusted_proxies knob must be a list of strings or a'
                . ' comma-separated string, int given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @dataProvider provideAnInvalidRedisHostHaltsCases
     */
    #[DataProvider('provideAnInvalidRedisHostHaltsCases')]
    public function testAnInvalidRedisHostHalts(mixed $value, string $type): void
    {
        try {
            RateLimitPolicy::fromMap(['redis_host' => $value]);
            self::fail('An invalid host must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.rate_limit redis_host knob must be a non-empty string, '
                . $type . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<array{mixed, string}>
     */
    public static function provideAnInvalidRedisHostHaltsCases(): iterable
    {
        yield 'an empty string' => ['', 'string'];

        yield 'an integer' => [42, 'int'];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldMap(RateLimitPolicy $rateLimitPolicy): array
    {
        return [
            'enabled' => $rateLimitPolicy->enabled,
            'limit' => $rateLimitPolicy->limit,
            'window' => $rateLimitPolicy->window,
            'max_keys' => $rateLimitPolicy->maxKeys,
            'store' => $rateLimitPolicy->store,
            'trusted_proxies' => $rateLimitPolicy->trustedProxies,
            'redis_host' => $rateLimitPolicy->redisHost,
            'redis_port' => $rateLimitPolicy->redisPort,
        ];
    }
}
