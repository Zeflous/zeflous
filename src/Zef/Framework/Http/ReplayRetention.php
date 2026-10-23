<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

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
 * Pruning answers the write path's own honesty rules: only snapshot files
 * (the `*.json` the writer emits) are ever considered, the modification
 * instant the writer's own filesystem records is the ordering witness, and a
 * prune that cannot unlink one file says so on the SAPI error log and keeps
 * walking -- a stuck file must not shield the older snapshots behind it, and
 * a housekeeping failure must never cost the request its just-written
 * snapshot's id (the capture contract the middleware's catch already owns).
 * The stat cache is cleared before reading, because a persistent worker
 * (FrankenPHP, the smoke lane's own) serves many requests from one process
 * and a cached instant of a since-unlinked file would order a ghost.
 */
final class ReplayRetention
{
    /**
     * Prunes the directory down to the newest snapshots within the bound.
     *
     * @param string $directory    the resolved replay storage directory
     * @param int    $maxSnapshots the retention bound (>= 1); the directory
     *                             keeps at most this many snapshot files
     */
    public static function prune(string $directory, int $maxSnapshots): void
    {
        $snapshots = self::snapshotsByAge($directory);

        $excess = \count($snapshots) - $maxSnapshots;
        if ($excess < 1) {
            return;
        }

        foreach (array_keys($snapshots) as $file) {
            if ($excess < 1) {
                break;
            }

            if (!unlink($file)) {
                error_log('ZEF error replay retention failed to remove: ' . $file);

                continue;
            }

            --$excess;
        }
    }

    /**
     * Answers the directory's snapshot files ordered oldest-first, keyed by
     * path with the modification instant as the value.
     *
     * @return array<string, int>
     */
    private static function snapshotsByAge(string $directory): array
    {
        clearstatcache();

        $files = glob($directory . \DIRECTORY_SEPARATOR . '*.json');

        $snapshots = [];
        foreach (\is_array($files) ? $files : [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            $modified = filemtime($file);
            $snapshots[$file] = \is_int($modified) ? $modified : 0;
        }

        asort($snapshots);

        return $snapshots;
    }
}
