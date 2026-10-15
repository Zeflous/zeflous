<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The per-process fixed-window bucket table: one `{count, reset}` bucket
 * per key, bounded by capacity -- the accounting half of the in-memory
 * rate limiter ({@see InMemoryRateLimiter}), extracted as its own unit.
 *
 * A bucket starts at its key's first request and rolls over whole when
 * its stored epoch passes ({@see BucketRollover}). Capacity is bounded:
 * a new key that arrives at capacity is admitted only after the expired
 * buckets are reclaimed, and an unreclaimable capacity is the reference's
 * fail-closed exhaustion error ({@see BucketRoom}) -- a limiter that
 * answered a verdict while silently forgetting its accounting is a
 * protection the operator believes is running while it is not, so
 * exhaustion is an error, never a pass.
 *
 * The reclaim is capacity-triggered (the port's own restructure of the
 * reference's every-check sweep, which was O(buckets) per request at the
 * default ten-thousand-key capacity): expired buckets are only worth
 * reclaiming when a new key needs the room, and existing-key rollovers
 * answer through the per-key epoch check either way -- the observable
 * verdicts are identical, and the hot path is O(1) amortised.
 *
 * Attribution: legacy `zef-main` `Framework/Security/
 * InMemoryRateLimiter.php` (the bucket accounting; extracted from the
 * limiter's check as its own collaborator, with the admission, the sweep
 * and the rollover each their own unit).
 */
final class BucketTable
{
    /** @var array<string, array{count:int, reset:int}> */
    private array $buckets = [];

    /**
     * @param int $capacity the bucket-table capacity bound (>= 1)
     *
     * @throws HttpException when the capacity bound is below one
     */
    public function __construct(
        private readonly int $capacity = 10000,
    ) {
        if ($this->capacity < 1) {
            throw new HttpException('The rate limiter max_keys knob must be 1 or above.');
        }
    }

    /**
     * Accounts one request for the key and answers its post-increment
     * bucket.
     *
     * @param string $key           the identity the bucket is keyed by
     * @param int    $windowSeconds the window length in seconds (>= 1)
     * @param int    $now           the caller's epoch, Unix seconds
     *
     * @return array{count:int, reset:int}
     *
     * @throws HttpException when a new key arrives at an unreclaimable
     *                       capacity
     */
    public function advance(string $key, int $windowSeconds, int $now): array
    {
        $this->buckets = BucketRoom::admitting($this->buckets, $key, $this->capacity, $now);

        $bucket = BucketRollover::advanced($this->buckets[$key] ?? null, $windowSeconds, $now);
        $this->buckets[$key] = $bucket;

        return $bucket;
    }
}
