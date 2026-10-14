<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Redis;

/**
 * The phpredis extension boundary: the four commands ({@see
 * RedisCommandsInterface}) as real `\Redis` calls over one connection.
 *
 * This is the family's only file that touches the optional extension, and
 * it stays thin enough to verify completely: the test suite covers it
 * through a process-isolated eval-defined `\Redis` class double (the test
 * environment carries no phpredis), which exercises the connection's
 * fail-closed refusal and every command delegation without a single
 * ignore entry in the mutation config.
 *
 * The connection is established here, at construction -- boot time for the
 * factory that builds the store -- and a refused connection fails closed
 * with the endpoint named: the reference constructed an unconnected
 * `\Redis` and let the first request's commands fatal into its
 * catch-all 503 instead, which answers the client with an outage the
 * operator never got told about at boot. A declared `store: redis` is a
 * statement that shared accounting is wanted; booting without it is the
 * honest refusal. Runtime command failures still answer through the
 * middleware's fail-closed 503 ({@see RateLimitMiddleware}), exactly the
 * reference's behaviour for a Redis that dies mid-flight.
 *
 * Attribution: legacy `zef-main` `Framework/Security/
 * RedisSharedRateLimitStore.php` (the command surface the reference
 * called on its `\Redis` member directly; extracted here so the window
 * semantics above it stay pure), plus the connect-fail remediation.
 */
final readonly class PhpRedisCommands implements RedisCommandsInterface
{
    private Redis $redis;

    /**
     * @param string $host the Redis endpoint's host
     * @param int    $port the Redis endpoint's port
     *
     * @throws HttpException when the connection is refused
     */
    public function __construct(string $host, int $port)
    {
        $this->redis = new Redis();

        if ($this->redis->connect($host, $port) === false) {
            throw new HttpException(
                "The redis rate-limit store could not connect to {$host}:{$port}.",
            );
        }
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
