<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The deferred arm's retry arithmetic: how many total delivery
 * attempts a delivery may spend, and how long the next one must
 * wait after the previous one failed. Exponential backoff is the
 * named demand (ED8's own spelling) and the only strategy here --
 * a second strategy would be a knob without a reader until a host
 * asks for it.
 *
 * The policy is pure arithmetic over integers the caller hands
 * in: it owns no clock, reads no time, and sleeps never -- the
 * drain's own {@see DrainOutcome} fold is the only place the
 * numbers meet a "now".
 */
final readonly class RetryPolicy
{
    public function __construct(
        private int $maxAttempts,
        private int $baseDelaySeconds,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException(
                'A retry policy must permit at least one delivery attempt,'
                . ' got ' . $maxAttempts . ' -- a policy of zero would'
                . ' dead-letter a delivery that never tried.',
            );
        }

        if ($baseDelaySeconds < 0) {
            throw new InvalidArgumentException(
                "A retry policy's base delay cannot be negative, got "
                . $baseDelaySeconds . '.',
            );
        }
    }

    /**
     * The total attempt ceiling: the first try included, so a
     * policy of one dead-letters on the first failure.
     */
    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * The backoff's base: the wait after the first failure; each
     * later failure doubles it. A base of zero degenerates to
     * "due at the next drain".
     */
    public function baseDelaySeconds(): int
    {
        return $this->baseDelaySeconds;
    }

    /**
     * Whether a delivery that just failed as the given attempt
     * number still has attempts left under this policy.
     */
    public function permitsAnotherAttempt(int $failedAttempt): bool
    {
        return $failedAttempt < $this->maxAttempts;
    }

    /**
     * The moment the next attempt becomes due: the drain's own
     * now plus the exponential backoff for the attempt that just
     * failed (base doubled once per attempt past the first),
     * capped at {@see PHP_INT_MAX} so a large base or a late
     * attempt can never overflow into a float -- the cap is
     * checked before every doubling AND before the final
     * addition, because the first failure's base alone can already
     * be the whole remaining integer range.
     */
    public function readyAtForNextAttempt(int $failedAttempt, int $now): int
    {
        $delay = $this->baseDelaySeconds;
        $headroom = \PHP_INT_MAX - $now;

        for ($doubling = 1; $doubling < $failedAttempt; ++$doubling) {
            if ($delay > $headroom >> 1) {
                return \PHP_INT_MAX;
            }

            $delay <<= 1;
        }

        if ($delay > $headroom) {
            return \PHP_INT_MAX;
        }

        return $now + $delay;
    }
}
