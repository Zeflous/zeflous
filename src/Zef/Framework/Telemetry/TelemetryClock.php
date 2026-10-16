<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Override;

/**
 * The telemetry clock's system implementation: `hrtime` for the
 * duration domain, `microtime` for the timestamp domain -- the
 * default every construction seam wires when no clock is injected
 * ({@see TracingFactory} does exactly that).
 *
 * The two reads are the engine's own counters, unpinned and
 * un-pinnable -- the suite never constructs this class for an
 * assertion; it injects the stepping double and reads the counters
 * there ({@see \Zef\Test\Support\Telemetry\SteppingTelemetryClock}).
 * The wall read's `(int)` cast is the float-to-int64 narrowing the
 * Unix-nanosecond representation is -- `microtime(true) *
 * 1_000_000_000` answers a float the int domain must land in, and
 * the cast is the one place that landing happens.
 */
final class TelemetryClock implements TelemetryClockInterface
{
    #[Override]
    public function monotonicNs(): int
    {
        // The cast is the engine signature's own narrowing: hrtime()
        // answers int|float, and the true-argument's int is not a
        // narrowing the analyser can carry through the call, so the
        // landing is explicit -- exactly as the wall read's is.
        return (int) hrtime(true);
    }

    #[Override]
    public function unixNano(): int
    {
        return (int) (microtime(true) * 1_000_000_000.0);
    }
}
