<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Temp;

use InvalidArgumentException;

/**
 * One scratch file's own retirement: the sweep's per-file
 * judgement -- the age measured against the retention the host
 * declared, retired when the retention fully elapsed, the
 * vanished file the retired state itself, never a refusal.
 *
 * THE MORTALITY BOUNDARY IS THE SESSION LANE'S OWN DISCIPLINE
 * ({@see \Zef\Framework\Adapters\Session\SessionRecordRetirement}):
 * a file is swept exactly when its own retention fully elapses
 * -- the timeout fully elapsed is mortal, the last instant
 * before it is not. The clock the lane's files carry is the
 * filesystem's own modified reading: the session record carries
 * its written-at word inside the payload it encodes, but a
 * scratch file IS its bytes and nothing else -- no sidecar
 * metadata, no encoded written-at, the one clock every file in
 * every tree already keeps for free. A file whose age the engine
 * cannot measure while the file exists is retired, never
 * skipped: the lane's files are mortal by declaration, so the
 * unmeasurable age is the defect the mortality itself answers --
 * a skipped defect would be an immortal file in a lane whose own
 * word is "nobody mourns it".
 *
 * THE RETIREMENT IDIOM IS THE SISTER FAMILY'S OWN ({@see
 * \Zef\Framework\Adapters\Session\SessionRecordFiles::removeRecord()}),
 * spelled for the sweep's own blast radius: the existence gate
 * first (a file a sibling worker retired between the scan and
 * this fold's own judgment is the retired state arrived at
 * through the window, never a refusal), then the removal with
 * the re-check the unlink's own false demands -- an un-unlinkable
 * file the walk names loudly, because the refusal riding the
 * write is the environment's own break, never a defect the
 * sweep could quarantine (a lane the process cannot write is a
 * lane the process's own writes already refused in).
 */
final readonly class TempRetirement
{
    /**
     * @param string $directory  the lane root the directory fold already
     *                           resolved ({@see TempStoreDirectory})
     * @param int    $ttlSeconds the retention, in seconds: every file
     *                           whose age fully elapses it is retired
     */
    public function __construct(
        private string $directory,
        private int $ttlSeconds,
    ) {
    }

    /**
     * Retires one scratch file when its own retention fully
     * elapsed.
     *
     * @param string $filename the scratch's own filename, the scan's answer
     * @param int    $now      the write's own clock reading
     *
     * @throws InvalidArgumentException when the file exists and
     *                                  cannot be removed
     */
    public function retire(string $filename, int $now): void
    {
        $path = $this->directory . \DIRECTORY_SEPARATOR . $filename;

        if (!is_file($path)) {
            return;
        }

        $modifiedAt = filemtime($path);

        if ($modifiedAt !== false && $now - $modifiedAt < $this->ttlSeconds) {
            return;
        }

        if (!unlink($path) && file_exists($path)) {
            throw new InvalidArgumentException(
                "The temp file store cannot remove the scratch '{$filename}'.",
            );
        }
    }
}
