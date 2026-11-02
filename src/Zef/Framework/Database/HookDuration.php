<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The slow-hook guard's timing fold: the two judgements the drain makes
 * per timed hook — how many whole milliseconds the hook's nanosecond
 * measurement folds up to, and whether that folded duration exceeds the
 * configured threshold.
 *
 * Pure folds over integers, the same seam the slow-request family
 * answers its own threshold through: the wall clock is measured where
 * the hook runs, but the DECISIONS are records the suite can pin at the
 * exact boundary (a duration that lands exactly on the threshold has
 * not exceeded it — the strict comparison), a boundary no sleep-based
 * test can spell deterministically.
 *
 * @internal
 */
final class HookDuration
{
    /**
     * The nanoseconds fold up to whole milliseconds (ceil, never floor:
     * a 100.1 microseconds hook already answers 1 ms).
     */
    public static function milliseconds(int $elapsedNs): int
    {
        return (int) ceil($elapsedNs / 1_000_000);
    }

    /**
     * True when a duration that already folded to whole milliseconds
     * exceeds the threshold bar. The comparison is strict — a duration
     * that lands exactly on the threshold has NOT exceeded it.
     */
    public static function exceeded(int $elapsedMs, int $thresholdMs): bool
    {
        return $elapsedMs > $thresholdMs;
    }
}
