<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Redis;

/**
 * The phpredis extension's command half: the three commands ({@see
 * RedisCommandsInterface}) as real delegations over one established
 * connection.
 *
 * This is one half of the family's phpredis boundary ({@see
 * PhpRedisConnection} is the connection half): the member is the seam's
 * union -- the extension's own `\Redis` in production (carried exactly
 * as the extension declares it, engine-checked on every runner that
 * carries it), or the test double through the interface arm ({@see
 * RedisConnectionInterface}), a real class whose answers are the
 * suite's own in every environment. The commands stay thin enough to
 * verify completely, and their mutants are killed without a single
 * ignore entry.
 *
 * Attribution: legacy `zef-main` `Framework/Security/
 * RedisSharedRateLimitStore.php` (the command surface the reference
 * called on its `\Redis` member directly; extracted here so the window
 * semantics above it stay pure).
 */
final readonly class PhpRedisCommands implements RedisCommandsInterface
{
    private Redis|RedisConnectionInterface $redis;

    /**
     * @param string                   $host            the Redis endpoint's host
     * @param int                      $port            the Redis endpoint's port
     * @param RedisConnectionInterface $redisConnection the client to delegate to (the
     *                                                  construction establishes the
     *                                                  extension's own by default; the
     *                                                  test suite injects its double)
     *
     * @throws HttpException when the connection cannot be established
     */
    public function __construct(
        string $host,
        int $port,
        ?RedisConnectionInterface $redisConnection = null,
    ) {
        $this->redis = PhpRedisConnection::establish($host, $port, $redisConnection);
    }

    #[Override]
    public function incr(string $key): int|string
    {
        return $this->redis->incr($key);
    }

    #[Override]
    public function ttl(string $key): false|int
    {
        return $this->redis->ttl($key);
    }

    #[Override]
    public function expire(string $key, int $seconds): void
    {
        $this->redis->expire($key, $seconds);
    }
}
