<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use InvalidArgumentException;

/**
 * Bounds the replay snapshot directory to the newest snapshots.
 *
 * The retention seam of the error-replay lane: after a snapshot is written,
 * the directory is pruned down to the configured bound, oldest first, so a
 * spamming error source in local development fills the bound and no further
 * -- the disk-filling accumulation the round-2 audit recorded as finding C-1
 * (#554, now #573) ends at a known, operator-sized ceiling instead of at the
 * filesystem's.
 *
 * The ordering witness itself lives in {@see ReplaySnapshotAge}; this seam
 * owns the bound's admission and the pruning walk's own honesty rules: the
 * walk attempts exactly the excess oldest snapshots (one slice, no loop
 * arithmetic a stuck entry could skew), and a prune that cannot unlink one
 * file says so on the SAPI error log and keeps walking -- a stuck file must
 * not shield the older snapshots behind it, and a housekeeping failure must
 * never cost the request its just-written snapshot's id (the capture
 * contract the middleware's catch already owns).
 */
final class ReplayRetention
{
    /**
     * Admits the retention bound, refusing anything below one: a bound of
     * zero or below is not "no retention" but a corrupted configuration,
     * and the capture path must never learn of it mid-request.
     *
     * @throws InvalidArgumentException when the bound is below one
     */
    public static function resolveBound(int $maxSnapshots): int
    {
        if ($maxSnapshots < 1) {
            throw new InvalidArgumentException('Replay snapshot retention must be 1 or above.');
        }

        return $maxSnapshots;
    }

    /**
     * Prunes the directory down to the newest snapshots within the bound.
     *
     * @param string $directory    the resolved replay storage directory
     * @param int    $maxSnapshots the retention bound (>= 1); the directory
     *                             keeps at most this many snapshot files
     */
    public static function prune(string $directory, int $maxSnapshots): void
    {
        $snapshots = ReplaySnapshotAge::orderedByAge($directory);

        $excess = \count($snapshots) - $maxSnapshots;
        if ($excess < 1) {
            return;
        }

        foreach (\array_slice(array_keys($snapshots), 0, $excess) as $file) {
            if (unlink($file)) {
                continue;
            }

            error_log('ZEF error replay retention failed to remove: ' . $file);
        }
    }
}
