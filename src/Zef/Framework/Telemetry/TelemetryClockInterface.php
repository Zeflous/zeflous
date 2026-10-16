<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The telemetry clock seam: the two time domains the tracing core
 * measures in, one named place -- the observability campaign's own
 * extension of the timing family's frozen-clock discipline ({@see
 * \Zef\Framework\Http\TimingClock}, KDD-OB4 in docs/ci-notes/
 * observability-runtime.md).
 *
 * The reference read both domains inline and statically (`hrtime`
 * and `microtime` called where the arithmetic needed them, three
 * consumption points across two classes); the port injects this one
 * seam instead, because a span's whole timeline -- the monotonic
 * start, every event's wall timestamp, the monotonic end -- must be
 * freezable as one double ({@see \Zef\Test\Support\Telemetry\
 * SteppingTelemetryClock}) for the suite to pin a span's exact
 * record without sleeping. The interface is the seam rather than a
 * closure because the two domains are one clock's two faces: a
 * closure per reading would scatter the seam across the tracer and
 * the span, and the mutation run cannot reach what it cannot name.
 *
 * `monotonicNs()` is the duration domain -- `hrtime`, immune to the
 * wall-clock adjustments (NTP steps, suspend/resume) that would
 * corrupt a span mid-measurement. `unixNano()` is the timestamp
 * domain -- the points in real time a timeline aligns spans from
 * different processes by; it is never arithmetic'd into a duration,
 * and the span derives its wall end from the monotonic delta rather
 * than reading the wall clock twice.
 */
interface TelemetryClockInterface
{
    /**
     * The current instant on the monotonic counter, in nanoseconds
     * -- the duration domain.
     */
    public function monotonicNs(): int;

    /**
     * The current instant on the wall clock, in Unix nanoseconds --
     * the timestamp domain.
     */
    public function unixNano(): int;
}
