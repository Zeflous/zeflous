<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Temp;

/**
 * The temp store's cadenced sweep: the fold that retires the
 * scratch whose retention fully elapsed -- the file a process
 * left behind and never came back for, replayed over the
 * filesystem exactly as the session store's own sweep spells it
 * ({@see \Zef\Framework\Adapters\Session\SessionSweep}).
 *
 * THE CADENCE AND THE RETENTION ARE SEPARATE CLOCKS: the cadence
 * bounds how often the walk runs (at most once per {@see
 * self::SWEEP_SECONDS}, riding a write the process was already
 * paying -- one lane walk per cadence, never a background
 * worker, the same cost bound the session lane spells), the
 * retention decides which files it takes. A file is swept
 * exactly when its own retention fully elapses, the same
 * boundary the retirement fold answers at ({@see
 * TempRetirement}).
 *
 * THE CADENCE IS THE LANE'S OWN MEMORY ({@see
 * TempSweepCadence}): the last-sweep reading is persisted in the
 * lane itself, because the store is composed per ask -- an
 * instance field would reset with every composition, and every
 * write-bearing ask would pay a full lane walk while the
 * sixty-second cadence promised the walk once a minute. The
 * marker's absence answers zero, the walk the store pays -- the
 * cadence is a cost bound, never a correctness one.
 */
final readonly class TempSweep
{
    /**
     * The sweep cadence: the write-riding pruning runs at most once
     * per this many seconds.
     */
    private const int SWEEP_SECONDS = 60;

    /**
     * @param TempSweepCadence $tempSweepCadence the cadence's own lane-persisted memory
     * @param TempScratchScan  $tempScratchScan  the store's own scan fold
     * @param TempRetirement   $tempRetirement   one file's own retirement judgement
     */
    public function __construct(
        private TempSweepCadence $tempSweepCadence,
        private TempScratchScan $tempScratchScan,
        private TempRetirement $tempRetirement,
    ) {
    }

    /**
     * The write's own sweep question: the cadence's answer first (a
     * write inside the cadence rides no walk), then the bounded
     * walk -- every scratch file judged against the retention, the
     * elapsed retired, the vanished and the unmeasurable handled by
     * the retirement fold's own idiom.
     *
     * @param int $now the write's own clock reading
     */
    public function rideWrite(int $now): void
    {
        if ($now - $this->tempSweepCadence->lastRanAt() < self::SWEEP_SECONDS) {
            return;
        }

        $this->tempSweepCadence->ranAt($now);

        foreach ($this->tempScratchScan->scratchFilenames() as $filename) {
            $this->tempRetirement->retire($filename, $now);
        }
    }
}
