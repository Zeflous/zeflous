<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\TelemetryClock;

/**
 * The system clock's own readings: monotonic nanoseconds and Unix
 * nanoseconds are the engine's live counters -- the suite never
 * asserts their values beyond the domains' own shape, because the
 * frozen timeline assertions live behind the stepping double
 * ({@see \Zef\Test\Support\Telemetry\SteppingTelemetryClock}) where
 * they belong.
 *
 * @internal
 */
#[CoversClass(TelemetryClock::class)]
final class TelemetryClockTest extends TestCase
{
    public function testTheMonotonicCounterAnswersNanoseconds(): void
    {
        $telemetryClock = new TelemetryClock();

        self::assertGreaterThanOrEqual(0, $telemetryClock->monotonicNs());
    }

    public function testTheWallClockAnswersUnixNanoseconds(): void
    {
        $telemetryClock = new TelemetryClock();

        self::assertGreaterThan(1_000_000_000, $telemetryClock->unixNano());
    }
}
