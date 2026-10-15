<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Atomic fixed-window rate-limit store shared across worker processes
 * (and, for Redis, across hosts).
 *
 * Implementations MUST be atomic for read-modify-write on one key so that
 * the aggregate limit is exact when N workers call concurrently: the
 * Redis implementation ({@see RedisSharedRateLimitStore}) runs one
 * server-side-atomic `INCR` per bucket, and the APCu implementation
 * ({@see ApcuCounterStore}) runs one `apcu_inc` per counter with an
 * `apcu_add`-claimed epoch. A per-process {@see InMemoryRateLimiter}
 * remains the zero-dependency default, but it is NOT shared across
 * workers (documented limitation, the reference's audit M-2).
 *
 * Attribution: legacy `zef-main` `Framework/Security/
 * SharedRateLimitStoreInterface.php` (the atomicity contract; the
 * reference's diagnostic `peek()` read is not ported -- no production
 * caller exists for it, and this repository carries no promises nothing
 * consumes).
 */
interface SharedRateLimitStoreInterface
{
    /**
     * Atomically increments the bucket for the key and answers the
     * post-increment count together with the window reset epoch (Unix
     * seconds).
     *
     * @param string $key           the identity the bucket is keyed by
     * @param int    $windowSeconds the window length in seconds (>= 1)
     * @param int    $now           the caller's epoch, Unix seconds
     *
     * @return array{count:int, reset:int}
     */
    public function increment(string $key, int $windowSeconds, int $now): array;
}
