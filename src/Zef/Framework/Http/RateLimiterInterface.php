<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * A fixed-window rate limiter: one `check()` call accounts one request
 * against the key's current window and answers the verdict.
 *
 * The contract the three stores share ({@see InMemoryRateLimiter} per
 * process, {@see ApcuRateLimiter} across one process tree,
 * {@see RedisRateLimiter} across every host pointing at the same Redis):
 * the key is the caller's identity string (the client address the
 * middleware resolved), `$limit` is the request budget per window, and
 * `$windowSeconds` is the window's length in seconds. The window is
 * *fixed* -- it starts when the key's first request arrives and resets
 * whole at the stored epoch -- which is the reference implementation's
 * documented model (legacy `zef-main`, milestone E2's M-2 remediation
 * notes), not a sliding or token bucket.
 *
 * Preconditions are guaranteed by the composition, once: the policy's
 * constructor invariants hold `limit` and `windowSeconds` at one or above,
 * and the address resolver never hands over an empty key. The reference
 * repeated these guards inside every store implementation; this port
 * validates at the seam instead ({@see RateLimitPolicy}, {@see
 * ClientAddress}) so the stores below carry the arithmetic only.
 *
 * Attribution: legacy `zef-main` `Framework/Security/RateLimiterInterface.php`
 * (move-verbatim signature).
 */
interface RateLimiterInterface
{
    /**
     * Accounts one request for the key and answers the window verdict.
     *
     * @param string $key           the identity the bucket is keyed by
     * @param int    $limit         the request budget per window (>= 1)
     * @param int    $windowSeconds the window length in seconds (>= 1)
     */
    public function check(string $key, int $limit, int $windowSeconds): RateLimitDecision;
}
