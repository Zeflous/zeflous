<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The timing family's clock: monotonic nanoseconds, one reading per
 * seam invocation -- the extracted seam the reference computed inline
 * (`hrtime(true)` at the request's start and again at its end, two
 * inline readings no test could freeze).
 *
 * The extraction follows the auth family's own discipline ({@see
 * AuthClock}, the Unix-milliseconds seam over the reference's twin
 * inline `microtime()` reads): the clock is one named place the suite
 * observes through the middleware's injectable seam, and `hrtime` is
 * the engine's monotonic counter -- the elapsed arithmetic it feeds
 * is immune to wall-clock adjustments (NTP steps, suspend/resume)
 * the way a wall clock never is.
 */
final class TimingClock
{
    /**
     * The current instant, in monotonic nanoseconds -- the only unit
     * the family's arithmetic ever sees.
     */
    public static function monotonicNs(): int
    {
        return (int) hrtime(true);
    }
}
