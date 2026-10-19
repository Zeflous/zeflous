<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised when the caller has exhausted the allowance an endpoint grants
 * them (ROADMAP.md, "Error Handling System", phase 1: the exception
 * hierarchy) -- the throttling signal (429).
 *
 * Carries the retry-after evidence: how many seconds the caller should
 * wait before asking again -- the value the error response's
 * `Retry-After` header is built from, so the mapper never guesses a
 * window the limiter already decided. A negative window is a defect
 * (the caller is never told to retry "in the past") and is refused
 * here, the same value-object discipline the declaration grammars hold.
 *
 * @api the typed family's published refusal vocabulary: hosts catch and
 *      throw these across their own code, and the rate-limit lane's
 *      throw site is still ahead -- the analyser cannot see either
 *      consumer from the framework tree alone.
 */
final class RateLimitException extends ZefException implements CarriesHttpStatus
{
    /**
     * @param int $retryAfterSeconds how long the caller waits before retrying
     */
    public function __construct(
        public readonly int $retryAfterSeconds,
        string $message = 'Too many requests.',
    ) {
        if ($retryAfterSeconds < 0) {
            throw new InvariantViolationException(
                'A rate-limit refusal cannot carry a negative retry-after window.',
            );
        }

        parent::__construct($message);
    }

    #[Override]
    public function httpStatus(): int
    {
        return 429;
    }
}
