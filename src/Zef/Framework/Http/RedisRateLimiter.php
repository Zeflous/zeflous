<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;
use Override;

/**
 * Fixed-window rate limiter backed by a shared store ({@see
 * RateLimiterInterface}) -- exact across the whole worker pool, and cross
 * host when the store is Redis.
 *
 * The limiter owns only the clock and the verdict arithmetic; the shared
 * store ({@see SharedRateLimitStoreInterface}) owns the atomic
 * read-modify-write. The clock is the same injectable `Closure(): int`
 * seam the other stores own (the reference read `time()` inline here;
 * the port keeps every store's window arithmetic deterministic under
 * test, which is the family-wide injectable-clock requirement).
 *
 * Attribution: legacy `zef-main` `Framework/Security/RedisRateLimiter.php`
 * (move-verbatim decisions and arithmetic; restructured construction: the
 * `maxKeys` constructor parameter the reference carried but never read is
 * not ported, and the clock becomes injectable).
 */
final readonly class RedisRateLimiter implements RateLimiterInterface
{
    /** @var Closure():int */
    private Closure $now;

    /**
     * @param SharedRateLimitStoreInterface $sharedRateLimitStore the atomic shared store
     * @param null|Closure():int            $now                  the clock seam, answering
     *                                                            Unix seconds; defaults to
     *                                                            `time()`
     */
    public function __construct(
        private SharedRateLimitStoreInterface $sharedRateLimitStore,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    #[Override]
    public function check(string $key, int $limit, int $windowSeconds): RateLimitDecision
    {
        $now = ($this->now)();
        ['count' => $count, 'reset' => $reset] = $this->sharedRateLimitStore->increment($key, $windowSeconds, $now);

        return RateLimitDecision::fromWindow($count, $limit, $reset, $now);
    }
}
