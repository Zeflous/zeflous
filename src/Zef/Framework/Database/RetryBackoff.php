<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use InvalidArgumentException;

/**
 * The retry backoff's compute: the exponential wait shaped by the
 * multiplier, capped by the ceiling, with optional randomised jitter
 * against thundering herds — the pure arithmetic the policy's delayMs
 * delegates to.
 *
 * @internal
 */
final class RetryBackoff
{
    /**
     * Milliseconds to wait before the next attempt.
     *
     * @param int $attempt 1-indexed: the attempt that just failed
     *
     * @throws InvalidArgumentException when the attempt is not positive
     */
    public static function delayMs(
        int $attempt,
        int $initialDelayMs,
        int $maxDelayMs,
        float $multiplier,
        int $jitterMs,
    ): int {
        if ($attempt < 1) {
            throw new InvalidArgumentException('Attempt must be positive.');
        }

        // The attempt is validated positive above, so the exponent
        // ($attempt - 1) is never negative and needs no floor clamp.
        $raw = (int) round(
            (float) $initialDelayMs * ($multiplier ** (float) ($attempt - 1)),
        );
        $delay = min($maxDelayMs, $raw);
        if ($jitterMs > 0) {
            $delay += random_int(0, $jitterMs);
        }

        return min($maxDelayMs, $delay);
    }
}
