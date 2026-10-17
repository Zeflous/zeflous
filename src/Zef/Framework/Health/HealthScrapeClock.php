<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

use Closure;

/**
 * The scrape's own clock: the monotonic seam the health readings
 * share -- the unit the {@see HealthAggregator} reads, extracted so
 * the deadline arithmetic and the elapsed reporting stay one named
 * place the suite freezes exactly.
 *
 * The seam is the timing family's own discipline ({@see
 * \Zef\Framework\Http\TimingClock}): the injectable closure keeps
 * the monotonic counter (immune to the wall-clock adjustments an
 * NTP step or a suspend would inflict mid-scrape), and the suite
 * wires its stepping double so the deadline arithmetic is a
 * deterministic test input rather than an untestable engine
 * behaviour.
 */
final readonly class HealthScrapeClock
{
    /** The wall-clock budget the whole scrape runs under, milliseconds. */
    private const int NANOS_PER_MILLISECOND = 1_000_000;

    /** The monotonic clock seam, one reading per invocation.
     *
     * @var Closure():int
     */
    private Closure $monotonicNs;

    /**
     * @param null|(Closure():int) $monotonicNs the monotonic clock seam,
     *                                          nanoseconds; defaults to the
     *                                          system monotonic clock
     */
    public function __construct(?Closure $monotonicNs = null)
    {
        $this->monotonicNs = $monotonicNs ?? static fn (): int => (int) hrtime(true);
    }

    /**
     * The current monotonic reading, nanoseconds.
     */
    public function now(): int
    {
        return ($this->monotonicNs)();
    }

    /**
     * The scrape's wall-clock deadline over its starting reading: the
     * budget translated to nanoseconds, or null when the scrape runs
     * unbounded.
     */
    public function deadline(int $started, ?int $overallTimeoutMs): ?int
    {
        if ($overallTimeoutMs === null) {
            return null;
        }

        return $started + $overallTimeoutMs * self::NANOS_PER_MILLISECOND;
    }

    /**
     * The elapsed milliseconds between the scrape's start and now,
     * the three-decimal shape the report carries.
     */
    public function elapsedMs(int $started): float
    {
        return round(($this->now() - $started) / self::NANOS_PER_MILLISECOND, 3);
    }
}
