<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The rate-limit family's validated knob set: the `security.rate_limit`
 * configuration subtree as one immutable value object.
 *
 * Construction through {@see self::fromMap()} is the config-driven path --
 * the merged map the layered builder hands over, where a knob's value can
 * be the native type a file declared or the raw string an environment
 * overlay wrote over it. Every knob is read strictly through the family's
 * shared readers ({@see StrictToggles} for the toggle, {@see
 * StrictIntegers} for the numeric knobs, {@see RateLimitStores} for the
 * closed store vocabulary, {@see TrustedProxyKnobs} for the proxy list),
 * the unknown-key census is {@see RateLimitKnobs}, and the range
 * invariants are {@see RateLimitBounds}: a wrong type, an out-of-range
 * value, an unknown store name or an unknown key halts the boot with the
 * knob named -- the operator believes the protection is running while it
 * is not otherwise.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration ({@see
 * \Zef\Framework\FrameworkDefaults}); the boot test pins the two
 * declarations together so they cannot drift.
 *
 * The knobs and their defaults follow the reference implementation's
 * posture (legacy `zef-main` `Framework/Security/SecurityPolicy.php`):
 * rate limiting is opt-in (`enabled` defaults to false), 100 requests per
 * 60 seconds, a ten-thousand-key capacity bound, the per-process in-
 * memory store, no trusted proxies, and the loopback Redis endpoint.
 * Two deliberate deviations, both fail-closed where the reference fell
 * silent: an unknown `store` name is rejected (the reference silently
 * read the in-memory default), and the integer knobs never clamp (`max(1,
 * ...)` in the reference silently promoted a mistyped zero).
 */
final readonly class RateLimitPolicy
{
    /**
     * @param list<string> $trustedProxies the exact addresses or CIDR
     *                                     networks allowed to supply
     *                                     `X-Forwarded-For`
     *
     * @throws HttpException when an integer knob is out of range
     */
    public function __construct(
        public bool $enabled = false,
        public int $limit = 100,
        public int $window = 60,
        public int $maxKeys = 10000,
        public string $store = RateLimitStores::MEMORY,
        public array $trustedProxies = [],
        public string $redisHost = '127.0.0.1',
        public int $redisPort = 6379,
    ) {
        RateLimitBounds::assert($this->limit, $this->window, $this->maxKeys, $this->redisPort);
    }

    /**
     * Reads the merged `security.rate_limit` map into the policy.
     *
     * @param mixed $map the subtree as the frozen repository carries it: a
     *                   knob map, or null when the family is absent
     *
     * @throws HttpException when the value is neither a map nor null, or a
     *                       knob fails its strict reading
     */
    public static function fromMap(mixed $map): self
    {
        if ($map === null) {
            return new self();
        }

        if (!\is_array($map)) {
            throw new HttpException(
                'The security.rate_limit configuration must be a map, ' . get_debug_type($map) . ' given.',
            );
        }

        RateLimitKnobs::rejectUnknown($map);

        return new self(...self::knobs($map));
    }

    /**
     * The family's knob map as constructor arguments: the admission
     * knobs and the store knobs, read strictly.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{enabled: bool, limit: int, window: int, maxKeys: int,
     *               store: string, trustedProxies: list<string>,
     *               redisHost: string, redisPort: int}
     */
    private static function knobs(array $map): array
    {
        return [
            ...self::admissionKnobs($map),
            ...self::storeKnobs($map),
        ];
    }

    /**
     * The admission knobs: whether the throttle is armed, the budget per
     * window, the window's length, and the memory store's capacity bound.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{enabled: bool, limit: int, window: int, maxKeys: int}
     */
    private static function admissionKnobs(array $map): array
    {
        return [
            'enabled' => StrictToggles::read($map['enabled'] ?? false, 'security.rate_limit enabled'),
            'limit' => StrictIntegers::read($map['limit'] ?? 100, 'security.rate_limit limit'),
            'window' => StrictIntegers::read($map['window'] ?? 60, 'security.rate_limit window'),
            'maxKeys' => StrictIntegers::read($map['max_keys'] ?? 10000, 'security.rate_limit max_keys'),
        ];
    }

    /**
     * The store knobs: the accounting topology, the trusted proxies the
     * client address honours, and the redis store's endpoint.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{store: string, trustedProxies: list<string>,
     *               redisHost: string, redisPort: int}
     */
    private static function storeKnobs(array $map): array
    {
        return [
            'store' => RateLimitStores::read($map['store'] ?? RateLimitStores::MEMORY),
            'trustedProxies' => TrustedProxyKnobs::read($map['trusted_proxies'] ?? []),
            'redisHost' => self::hostString($map['redis_host'] ?? '127.0.0.1'),
            'redisPort' => StrictIntegers::read($map['redis_port'] ?? 6379, 'security.rate_limit redis_port'),
        ];
    }

    /**
     * The Redis endpoint's host: a non-empty string (a hostname or a
     * literal address -- the phpredis `connect()` grammar decides, not this
     * reader).
     */
    private static function hostString(mixed $value): string
    {
        if (\is_string($value) && $value !== '') {
            return $value;
        }

        throw new HttpException(
            'The security.rate_limit redis_host knob must be a non-empty string, '
            . get_debug_type($value) . ' given.',
        );
    }
}
