<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Redis;

/**
 * The phpredis extension's command half: the three commands ({@see
 * RedisCommandsInterface}) as real `\Redis` calls over one established
 * connection.
 *
 * This is one half of the family's phpredis boundary ({@see
 * PhpRedisConnection} is the connection half): the commands stay thin
 * enough to verify completely -- the test suite exercises them against
 * a class double in every environment (a subclass of the real `\Redis`
 * where the extension is present, an eval-defined shell where it is
 * not), injected through the constructor's connection argument, so
 * every delegation is covered regardless of the runner's extension
 * set.
 *
 * Attribution: legacy `zef-main` `Framework/Security/
 * RedisSharedRateLimitStore.php` (the command surface the reference
 * called on its `\Redis` member directly; extracted here so the window
 * semantics above it stay pure).
 */
final readonly class PhpRedisCommands implements RedisCommandsInterface
{
    private Redis $redis;

    /**
     * @param string $host  the Redis endpoint's host
     * @param int    $port  the Redis endpoint's port
     * @param Redis  $redis the connection to delegate to (the
     *                      construction establishes one by default; the
     *                      test suite injects its double)
     *
     * @throws HttpException when the connection cannot be established
     */
    public function __construct(string $host, int $port, ?Redis $redis = null)
    {
        $this->redis = PhpRedisConnection::establish($host, $port, $redis);
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
