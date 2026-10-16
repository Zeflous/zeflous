<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The span's timeline: the measurement half of a span's lifecycle --
 * the monotonic start the span opens with, the monotonic end it
 * closes with, and the wall-clock start and derived wall-clock end
 * the record carries, all in one named place.
 *
 * The timeline owns the arithmetic the campaign's clock discipline
 * assigns it (KDD-OB11 in docs/ci-notes/observability-runtime.md):
 * one wall reading per span (the start, taken as the timeline
 * opens), the monotonic seam doing the measuring (the end's clamp
 * -- a reading before the start is a broken clock, never a negative
 * duration), and the wall end derived from the monotonic delta
 * (`startUnixNano + endNs - startNs`) rather than read -- so a
 * wall-clock adjustment between the two monotonic reads cannot bend
 * a span's apparent duration.
 *
 * The timeline does not know when it closes: the span's own
 * lifecycle ({@see Span::$ended}) is the one guard, and {@see
 * self::close()} is the measurement it performs when the span
 * calls it exactly once.
 */
final class SpanTimeline
{
    private readonly int $startNs;

    private readonly int $startUnixNano;

    private int $endNs = 0;

    public function __construct(private readonly TelemetryClockInterface $telemetryClock)
    {
        $this->startNs = $this->telemetryClock->monotonicNs();
        $this->startUnixNano = $this->telemetryClock->unixNano();
    }

    /**
     * The event timestamp the wall clock answers right now -- the
     * one reading the timeline takes after the span has opened
     * (each event's own timestamp, the timestamp domain, never the
     * duration domain).
     */
    public function wallNow(): int
    {
        return $this->telemetryClock->unixNano();
    }

    /**
     * The monotonic end the span closes with: the explicit reading
     * when the caller measured the span itself, the clock's
     * otherwise, clamped to never precede the start.
     */
    public function close(?int $endNs): void
    {
        $this->endNs = max($this->startNs, $endNs ?? $this->telemetryClock->monotonicNs());
    }

    /**
     * The monotonic start, nanoseconds.
     */
    public function startNs(): int
    {
        return $this->startNs;
    }

    /**
     * The monotonic end, nanoseconds.
     */
    public function endNs(): int
    {
        return $this->endNs;
    }

    /**
     * The wall-clock start, Unix nanoseconds.
     */
    public function startUnixNano(): int
    {
        return $this->startUnixNano;
    }

    /**
     * The wall-clock end, Unix nanoseconds -- derived from the
     * monotonic delta, never read from the wall clock.
     */
    public function endUnixNano(): int
    {
        return $this->startUnixNano + $this->endNs - $this->startNs;
    }
}
