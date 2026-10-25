<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The slow-request family's bounds: the one range invariant the
 * family holds, asserted at the policy's construction -- the
 * {@see SessionBounds} pattern over the threshold knob.
 *
 * The corridor (1..3_600_000, one millisecond to one hour) exists
 * for the nonsense spellings a bare integer knob invites: the
 * floor keeps the knob honest against the zero-millisecond bar
 * (which would record literally every request -- a metrics
 * family's job, not a slow-request log's), the ceiling against the
 * hour-deep bar (a request a full hour deep is already a pathology
 * every lane records) and the stray-suffix magnitudes beyond it. A
 * legal-but-mistyped unit value stays legal -- a threshold spelled
 * `2` where two SECONDS was meant judges every request slow at two
 * milliseconds -- because no corridor can read an operator's
 * intent; the record's own `threshold_ms` fact carries the bar it
 * was judged against, so an operator reading a flood of records
 * against a bar they did not mean sees the number to correct.
 *
 * The assert answers at the constructor rather than the map reading
 * so the corridor holds at BOTH construction paths: the config-driven
 * one ({@see SlowRequestPolicy::fromMap()}) and the programmatic one
 * (direct construction) -- a host spelling an out-of-corridor value
 * by hand gets the same named halt an operator's typo gets.
 */
final class SlowRequestBounds
{
    private const int MIN_THRESHOLD_MS = 1;

    private const int MAX_THRESHOLD_MS = 3600000;

    /**
     * Asserts the threshold's corridor.
     *
     * @param int $thresholdMs the bar a request must strictly exceed to
     *                         be recorded, in milliseconds
     *
     * @throws HttpException when the threshold is outside 1..3600000
     */
    public static function assertThresholdMs(int $thresholdMs): void
    {
        if ($thresholdMs < self::MIN_THRESHOLD_MS || $thresholdMs > self::MAX_THRESHOLD_MS) {
            throw new HttpException(
                'The observability.slow_request threshold_ms knob must be between 1 and 3600000, '
                . $thresholdMs . ' given.',
            );
        }
    }
}
