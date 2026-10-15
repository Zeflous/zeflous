<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The three native Redis commands the shared rate-limit store is built
 * on, in the phpredis reply shapes ({@see RedisSharedRateLimitStore}).
 *
 * The interface exists so the store's window semantics are pure and
 * connection-agnostic -- testable against an in-memory double -- while the
 * extension boundary ({@see PhpRedisCommands}) stays thin enough to verify
 * through the seam's union -- the extension's own \Redis in
 * production, the suite's real class double through the interface arm
 * ({@see RedisConnectionInterface}) in every environment. Only native commands appear
 * (`INCR`, `TTL`, `EXPIRE`): the reference implementation's own
 * remediation note records that its first design ran a Lua script through
 * `Redis::eval()` and its second used `WATCH`/`MULTI`/`EXEC` -- both
 * rejected by its static policy scanner, and both heavier than the
 * INCR-atomic pattern that answers the same aggregate-exactness contract.
 *
 * `INCR` is atomic server-side, which is the whole point: N workers
 * (across hosts) incrementing the same key count exactly N between them,
 * with no read-modify-write window to race in.
 */
interface RedisCommandsInterface
{
    /**
     * Increments the integer at the key, answering the post-increment
     * value (the phpredis reply: an int, or a numeric string under
     * non-default reply options).
     */
    public function incr(string $key): int|string;

    /**
     * Answers the key's remaining TTL in seconds, `-1` for a key with no
     * TTL and `-2` for a key that does not exist.
     */
    public function ttl(string $key): false|int;

    /**
     * Sets the key's TTL in seconds.
     */
    public function expire(string $key, int $seconds): void;
}
