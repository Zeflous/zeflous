<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;
use Override;

/**
 * Per-process in-memory fixed-window rate limiter -- the zero-dependency
 * default store ({@see RateLimiterInterface}).
 *
 * The clock is an injectable `Closure(): int` seam -- production runs the
 * system `time()`, tests inject a fixed epoch so the window arithmetic,
 * the rollover and the `Retry-After` derivation are all deterministic --
 * and the accounting itself lives in the bounded {@see BucketTable}
 * collaborator; the verdict is the family's shared window arithmetic.
 *
 * DOCUMENTED LIMITATION (the reference's audit M-2, kept verbatim in
 * spirit): state lives inside one PHP process. Under a persistent-worker
 * pool every worker carries its own instance, so the effective limit is
 * multiplied by the worker count and counters are lost whenever a worker
 * recycles. For exact, shared accounting use a store shared across the
 * pool: {@see ApcuRateLimiter} (one process tree) or {@see
 * RedisRateLimiter} (every host), selected through the `security
 * .rate_limit.store` knob.
 *
 * Attribution: legacy `zef-main` `Framework/Security/InMemoryRateLimiter.php`
 * (move-verbatim decisions and exhaustion error; restructured into the
 * bucket-table collaborator and the shared verdict factory, with the
 * reference's every-check sweep made capacity-triggered -- identical
 * verdicts, O(1) amortised hot path).
 */
final readonly class InMemoryRateLimiter implements RateLimiterInterface
{
    private BucketTable $bucketTable;

    /** @var Closure():int */
    private Closure $now;

    /**
     * @param int                $maxKeys the bucket-table capacity bound (>= 1)
     * @param null|Closure():int $now     the clock seam, answering Unix
     *                                    seconds; defaults to `time()`
     */
    public function __construct(
        int $maxKeys = 10000,
        ?Closure $now = null,
    ) {
        $this->bucketTable = new BucketTable($maxKeys);
        $this->now = $now ?? time(...);
    }

    #[Override]
    public function check(string $key, int $limit, int $windowSeconds): RateLimitDecision
    {
        $now = ($this->now)();
        $bucket = $this->bucketTable->advance($key, $windowSeconds, $now);

        return RateLimitDecision::fromWindow($bucket['count'], $limit, $bucket['reset'], $now);
    }
}
