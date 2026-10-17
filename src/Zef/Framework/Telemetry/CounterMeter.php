<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Override;

/**
 * The metrics lane's in-process engine: a bounded counter and
 * observation store implementing {@see MeterInterface} -- the
 * campaign's metrics family lands it exactly where the tracing
 * core's porting notes reserved the arrival.
 *
 * THE ENGINE IS THE ACCUMULATION over the {@see MeterTable}'s bound
 * and the {@see MeterDimensions}' name policy: `increment()` counts
 * events (the counter -- a monotonic total), `observe()` records
 * measurements against a name (the histogram-style series -- a count
 * plus a sum), and `snapshot()` answers the table the exposition
 * renders. The two counting methods share the one accumulation path
 * -- the counter's count and sum advance together, the observation's
 * count advances by one and its sum by the measured value.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Observability/CounterMeter.php` and its
 * `MeterInterface`): the bound, the overflow bucket, the eviction,
 * the dimension allow-lists, the ksort and the composite key all
 * move verbatim. The port's own deviations: the sanitisation the
 * reference wired inline is this repository's own {@see
 * TelemetrySanitizer} unit (the hygiene family's own census -- the
 * one the campaign landed one family early, exactly so this lane
 * reads it), and the dimension policy, the bounded table and the
 * slot identity live in their own units ({@see MeterDimensions},
 * {@see MeterTable}, {@see MeterSlot}) so each stays one named place
 * the suite reads directly.
 */
final readonly class CounterMeter implements MeterInterface
{
    /** The bounded table the engine records through. */
    private MeterTable $meterTable;

    public function __construct()
    {
        $this->meterTable = new MeterTable();
    }

    #[Override]
    public function increment(string $name, float|int $value = 1, array $attributes = []): void
    {
        $this->accumulate($name, $value, (float) $value, $attributes);
    }

    #[Override]
    public function observe(string $name, float $value, array $attributes = []): void
    {
        $this->accumulate($name, 1, $value, $attributes);
    }

    #[Override]
    public function snapshot(): array
    {
        return $this->meterTable->snapshot();
    }

    /**
     * The one accumulation path both counting methods share: resolve
     * the recording's slot in the bounded table, advance the series
     * it holds, record it back.
     *
     * @param string              $name       the metric name
     * @param float|int           $countBy    the count's advance
     * @param float               $sumBy      the sum's advance
     * @param array<string,mixed> $attributes the recorded attribute map
     */
    private function accumulate(string $name, float|int $countBy, float $sumBy, array $attributes): void
    {
        $slot = $this->meterTable->slot($name, $attributes);
        $series = $this->meterTable->series($slot);
        $series['count'] = MeterCount::advanced($series['count'], $countBy);
        $series['sum'] += $sumBy;
        $this->meterTable->write($slot, $series);
    }
}
