<?php

declare(strict_types=1);

namespace Zef\Test\Support\Telemetry;

use Override;
use Zef\Framework\Telemetry\TelemetryClockInterface;

/**
 * Stepping telemetry clock double: the injectable clock seam whose
 * every read advances its domain's answer by a fixed step -- the
 * shape the tracing core's span timeline needs (one monotonic read
 * at the span's open, one wall read at the open, one wall read per
 * event, one monotonic read at the end), so a test freezes the
 * span's whole record to exact values without sleeping.
 *
 * The two domains step independently, because the span reads them
 * independently: the monotonic epoch and step pin the duration (the
 * end's monotonic read lands `epoch + step`), the wall epoch and
 * step pin the timestamps (the start's wall read lands the epoch,
 * each event's the epoch plus one wall step). The read counters are
 * the zero-read guard's observable -- a disabled tracer pins both
 * at zero, exactly as the timing family's inert posture pinned its
 * single clock's counter.
 */
final class SteppingTelemetryClock implements TelemetryClockInterface
{
    private int $monotonicReads = 0;

    private int $wallReads = 0;

    public function __construct(
        public int $monotonicEpoch = 0,
        public int $monotonicStep = 0,
        public int $wallEpoch = 0,
        public int $wallStep = 0,
    ) {
    }

    #[Override]
    public function monotonicNs(): int
    {
        return $this->monotonicEpoch + $this->monotonicStep * $this->monotonicReads++;
    }

    #[Override]
    public function unixNano(): int
    {
        return $this->wallEpoch + $this->wallStep * $this->wallReads++;
    }

    /**
     * The monotonic reads the clock has answered so far -- the count a
     * test pins when it asserts a path never measured.
     */
    public function monotonicReadCount(): int
    {
        return $this->monotonicReads;
    }

    /**
     * The wall reads the clock has answered so far -- the count a test
     * pins when it asserts a timeline was never opened.
     */
    public function wallReadCount(): int
    {
        return $this->wallReads;
    }
}
