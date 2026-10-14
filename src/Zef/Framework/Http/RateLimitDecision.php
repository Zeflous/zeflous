<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * One fixed-window rate-limit verdict: what the limiter answered about the
 * request it just accounted.
 *
 * The four fields are the contract the middleware renders onto the wire --
 * `X-RateLimit-Limit` and `X-RateLimit-Remaining` from the success path,
 * `Retry-After` from the denial -- so every store implementation answers
 * the same shape ({@see RateLimiterInterface}). `remaining` is clamped at
 * zero by the producing verdict (a bucket over its limit reports `0`, never
 * a negative), and `retryAfter` is clamped at one second (the caller never
 * learns "retry immediately" for a window that has already rolled over).
 *
 * {@see self::fromWindow()} is the family's shared arithmetic: the three
 * stores answer their bucket the same way, and the verdict folds the
 * bucket into the decision every consumer reads.
 *
 * Attribution: legacy `zef-main` `Framework/Security/RateLimitDecision.php`
 * (move-verbatim shape: allowed, limit, remaining, retryAfter; the window
 * factory is the port's extraction of the arithmetic all three of the
 * reference's limiter implementations repeated).
 */
final readonly class RateLimitDecision
{
    public function __construct(
        public bool $allowed,
        public int $limit,
        public int $remaining,
        public int $retryAfter,
    ) {
    }

    /**
     * The verdict for one accounted bucket: allowed while the bucket's
     * count is within the limit, with the remaining budget clamped at
     * zero and the retry window clamped at one second.
     *
     * @param int $count the bucket's post-increment count
     * @param int $limit the window's request budget
     * @param int $reset the window's reset epoch, Unix seconds
     * @param int $now   the caller's epoch, Unix seconds
     */
    public static function fromWindow(int $count, int $limit, int $reset, int $now): self
    {
        return new self(
            $count <= $limit,
            $limit,
            max(0, $limit - $count),
            max(1, $reset - $now),
        );
    }
}
