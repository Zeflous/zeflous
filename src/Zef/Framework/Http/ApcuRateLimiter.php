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
 * use {@see RedisRateLimiter}). Availability is the deployment's own to
 * guarantee, not the factory's to check ({@see RateLimitFactory} spells
 * the doctrine: no environment-dependent branch): the extension absent
 * (or `apc.enable_cli` off for a CLI worker), the store's calls answer
 * false and the middleware's catch-all answers the fail-closed 503 on
 * every request of the runner that cannot honour the `store: apcu`
 * declaration -- per request, never a boot halt with a remediation
 * spelled out.
 *
 * DOCUMENTED LIMITATION (the reference's own, audit M-2, issue #785): the
 * counter and epoch are two keys written in two steps, so a window
 * boundary can skew by microseconds between them -- the counter key
 * outlives the epoch by exactly the skew, and a request landing inside
 * that gap reads the new epoch against the old window's still-living
 * count (a verdict the `Retry-After` still reports against the new
 * epoch's fuller window). The verdict's direction stays conservative
 * (the skew only ever *lengthens* the reported `Retry-After`, never
 * shortens it), but the aggregate count per window is not exact across
 * that boundary: the new epoch's first verdicts can carry the previous
 * window's tail in their count.
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
