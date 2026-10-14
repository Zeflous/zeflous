<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;
use Override;

/**
 * APCu-backed fixed-window rate limiter -- the store shared across one
 * process tree ({@see RateLimiterInterface}).
 *
 * Fixed-window accounting over the two atomic primitives ({@see
 * ApcuCounterStoreInterface}): the reset epoch is claimed once per window
 * (`apcu_add`, set-only-if-absent -- the first request of the window fixes
 * the epoch everyone else reads), and the counter is one atomic
 * `apcu_inc` step whose TTL is the window itself, so the counter dies with
 * its window and the next request re-initialises it at one. The clock is
 * the same injectable `Closure(): int` seam the in-memory limiter owns,
 * keeping the window arithmetic deterministic under test.
 *
 * Safe for a persistent-worker pool that shares one PHP process tree
 * (APCu is per-process-pool, not cross-host -- for cross-host accounting
 * use {@see RedisRateLimiter}). Requires the APCu extension and
 * `apc.enable_cli=1` for CLI workers; the factory checks availability
 * before construction and fails closed with the remediation spelled out.
 *
 * DOCUMENTED LIMITATION (the reference's own, audit M-2): the counter and
 * epoch are two keys written in two steps, so a window boundary can skew
 * by microseconds between them; the skew only ever *lengthens* the
 * reported `Retry-After` (an epoch outliving its counter keeps the
 * verdict conservative), never shortens it, and the aggregate count per
 * window stays exact because each key's write is individually atomic.
 *
 * Attribution: legacy `zef-main` `Framework/Security/ApcuRateLimiter.php`
 * (move-verbatim decisions: the `zef:ratelimit:` key prefix with the
 * sha256-hashed identity, the clamped remaining/retryAfter arithmetic;
 * restructured storage: the reference's racing `apcu_fetch`/`apcu_store`
 * pair becomes the atomic increment/claim pair its own interface
 * docblock required).
 */
final readonly class ApcuRateLimiter implements RateLimiterInterface
{
    private const string KEY_PREFIX = 'zef:ratelimit:';

    /** @var Closure():int */
    private Closure $now;

    /**
     * @param ApcuCounterStoreInterface $apcuCounterStore the atomic counter primitives
     * @param null|Closure():int        $now              the clock seam, answering Unix
     *                                                    seconds; defaults to `time()`
     */
    public function __construct(
        private ApcuCounterStoreInterface $apcuCounterStore,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    #[Override]
    public function check(string $key, int $limit, int $windowSeconds): RateLimitDecision
    {
        $now = ($this->now)();

        $reset = $this->apcuCounterStore->claimResetEpoch(
            self::KEY_PREFIX . 'reset:' . hash('sha256', $key),
            $now + $windowSeconds,
            $windowSeconds,
        );
        $count = $this->apcuCounterStore->increment(
            self::KEY_PREFIX . 'count:' . hash('sha256', $key),
            $windowSeconds,
        );

        return RateLimitDecision::fromWindow($count, $limit, $reset, $now);
    }
}
