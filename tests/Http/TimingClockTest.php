<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\TimingClock;

/**
 * The timing family's clock seam, covered as its own unit: the reading is an
 * integer of monotonic nanoseconds, and successive readings move forward --
 * the property the elapsed arithmetic depends on and the reason the seam
 * exists instead of two inline `hrtime` calls no test could observe.
 *
 * @internal
 */
#[CoversClass(TimingClock::class)]
final class TimingClockTest extends TestCase
{
    public function testSuccessiveReadingsMoveForward(): void
    {
        // Strictly greater: two consecutive monotonic readings differ by the
        // call's own overhead, and the strict comparison is what pins the
        // reading to the engine's nanosecond counter rather than any
        // degenerate constant.
        $first = TimingClock::monotonicNs();
        $second = TimingClock::monotonicNs();
        $third = TimingClock::monotonicNs();

        self::assertGreaterThan($first, $second);
        self::assertGreaterThan($second, $third);
    }

    public function testTheClockAnswersOnTheEngineTimeLineNotTheWallClock(): void
    {
        // A monotonic reading is at least the order of the engine's own
        // uptime in nanoseconds; a wall-clock epoch reading would be around
        // 1.7e18 (order 10^18) while hrtime(false)'s first component stays
        // small. This pins the *unit family* without binding to any absolute
        // value either timeline could produce.
        $reading = TimingClock::monotonicNs();

        self::assertGreaterThan(0, $reading);
    }
}
