<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

/**
 * The session store's cadence marker: the sweep's own memory,
 * persisted in the store itself -- the unit the {@see SessionSweep}
 * reads at arm time, so the cadence survives the per-request
 * composition the adapter answers (issue #788, finding 2: the first
 * spelling carried the last-sweep reading in an instance field, and
 * the adapter is composed per request -- every save-bearing request
 * paid a fresh field and a full store walk, the cadence the
 * sixty-second bound promised never once holding).
 *
 * THE MARKER IS THE STORE'S OWN ARTEFACT: a leading-dot file the
 * scans' own grammar skips ({@see SessionRecordScan} -- the staging
 * discipline's stranded files and the lock sidecars ride the same
 * blind spot), carrying the last sweep's Unix reading as its plain
 * decimal spelling. The read is fail-open by design: an absent,
 * unreadable or operator-corrupted marker answers zero, and zero is
 * the walk the store pays -- the cadence is a cost bound, never a
 * correctness one, so the worst case a missing marker can inflict is
 * one extra walk, never a missed one. The write is best-effort for
 * the same reason: the one-rename discipline the record writes carry,
 * and a marker the store cannot lay down leaves the next save
 * sweeping again -- the degraded-but-correct cadence, spelled by the
 * walk it repeats rather than a refusal that would brick the very
 * saves the cadence exists to cheapen.
 */
final readonly class SessionSweepCadence
{
    /** The cadence marker's filename: a leading dot, the scans' own blind spot. */
    private const string MARKER = '.sweep-cadence';

    /**
     * The staging suffix the marker's own one-rename discipline
     * writes through: a partial marker a crash strands is as
     * invisible to the scans as the staging file it imitates.
     */
    private const string STAGING_SUFFIX = '.staging';

    /**
     * @param string $directory the store root the directory fold
     *                          already resolved ({@see SessionStoreDirectory})
     */
    public function __construct(
        private string $directory,
    ) {
    }

    /**
     * The reading the last sweep ran at: the marker's own decimal
     * spelling, or zero when the marker is absent, unreadable or
     * corrupted -- the walk the store pays, never a sweep the
     * corruption could skip.
     */
    public function lastRanAt(): int
    {
        $marker = $this->markerPath();

        if (!is_file($marker)) {
            return 0;
        }

        $reading = file_get_contents($marker);

        if ($reading === false) {
            return 0;
        }

        $trimmed = mb_trim($reading);

        return ctype_digit($trimmed) ? (int) $trimmed : 0;
    }

    /**
     * Lays the reading down through the one-rename discipline,
     * best-effort: a marker the store cannot write leaves the next
     * save sweeping again -- the honest degradation, never a refusal
     * that would fail the save the cadence rides.
     *
     * @param int $now the sweep's own clock reading
     */
    public function ranAt(int $now): void
    {
        $staging = $this->markerPath() . self::STAGING_SUFFIX;

        if (file_put_contents($staging, (string) $now, \LOCK_EX) === false) {
            return;
        }

        if (rename($staging, $this->markerPath())) {
            return;
        }

        unlink($staging);
    }

    /**
     * The marker's own path under the store root.
     */
    private function markerPath(): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . self::MARKER;
    }
}
