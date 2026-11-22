<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Temp;

/**
 * The temp store's cadence marker: the sweep's own memory,
 * persisted in the lane itself -- the unit the {@see TempSweep}
 * reads at ride time, so the cadence survives the per-ask
 * composition the transient registration answers (the session
 * store's own doctrine, issue #788, finding 2: an instance field
 * would reset with every composition, and every write-bearing ask
 * would pay a full lane walk while the sixty-second cadence
 * promised the walk once a minute).
 *
 * THE MARKER IS THE LANE'S OWN ARTEFACT: a leading-dot file the
 * scan's own grammar skips ({@see TempScratchScan} -- the staging
 * discipline's stranded files ride the same blind spot), carrying
 * the last sweep's Unix reading as its plain decimal spelling.
 * The read is fail-open by design: an absent, unreadable or
 * operator-corrupted marker answers zero, and zero is the walk
 * the store pays -- the cadence is a cost bound, never a
 * correctness one, so the worst case a missing marker can
 * inflict is one extra walk, never a missed one. The write is
 * best-effort for the same reason: the one-rename discipline the
 * scratch writes carry, and a marker the store cannot lay down
 * leaves the next write sweeping again -- the degraded-but-correct
 * cadence, spelled by the walk it repeats rather than a refusal
 * that would brick the very writes the cadence exists to cheapen.
 */
final readonly class TempSweepCadence
{
    /** The cadence marker's filename: a leading dot, the scan's own blind spot. */
    private const string MARKER = '.sweep-cadence';

    /**
     * The staging suffix the marker's own one-rename discipline
     * writes through: a partial marker a crash strands is as
     * invisible to the scan as the staging file it imitates.
     */
    private const string STAGING_SUFFIX = '.staging';

    /**
     * @param string $directory the lane root the directory fold
     *                          already resolved ({@see TempStoreDirectory})
     */
    public function __construct(
        private string $directory,
    ) {
    }

    /**
     * The reading the last sweep ran at: the marker's own decimal
     * spelling, or zero when the marker is absent, unreadable or
     * corrupted -- the walk the store pays, never a sweep the
     * corruption could skip. The fold spells its absent-and-unreadable
     * answer as ONE ternary guard (the session lane's cadence folds
     * the same question as two separate refusals -- the family's own
     * spelling, the density bound's own answer).
     */
    public function lastRanAt(): int
    {
        $marker = $this->markerPath();
        $reading = is_file($marker) ? file_get_contents($marker) : false;

        if ($reading === false) {
            return 0;
        }

        $trimmed = mb_trim($reading);

        return ctype_digit($trimmed) ? (int) $trimmed : 0;
    }

    /**
     * Lays the reading down through the one-rename discipline,
     * best-effort: a marker the store cannot write leaves the next
     * write sweeping again -- the honest degradation, never a
     * refusal that would fail the write the cadence rides. The lane
     * address is HOISTED once (the session lane's cadence fold spells
     * its rename destination as a second walk of the same path -- the
     * family's own spelling, the density bound's own answer).
     *
     * @param int $now the sweep's own clock reading
     */
    public function ranAt(int $now): void
    {
        $marker = $this->markerPath();
        $staging = $marker . self::STAGING_SUFFIX;

        if (file_put_contents($staging, (string) $now, \LOCK_EX) === false) {
            return;
        }

        if (!rename($staging, $marker)) {
            unlink($staging);
        }
    }

    /**
     * The marker's own path under the lane root.
     */
    private function markerPath(): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . self::MARKER;
    }
}
