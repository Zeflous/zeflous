<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Answers the replay snapshot files a directory holds, ordered oldest-first.
 *
 * The ordering witness is the filesystem's own modification instant -- the
 * writer's own clock, no snapshot content trusted. An entry the filesystem
 * refuses to stat (a dead symlink, a vanished-in-flight path) answers 0 and
 * therefore sorts as the oldest garbage there is, so a pruning walk removes
 * it first -- the honest fate for an entry that is not a readable snapshot.
 * No explicit stat-cache clearing is needed: PHP clears the cache itself for
 * the files its own unlink removes, and the writer's 128-bit random ids never
 * revisit a path an earlier walk cached.
 */
final class ReplaySnapshotAge
{
    /**
     * Answers the directory's snapshot files with their modification
     * instants, ordered oldest-first.
     *
     * @return array<string, int> the snapshot paths, keyed by path, valued
     *                            by the modification instant (0 when the
     *                            filesystem answers none), oldest first
     */
    public static function orderedByAge(string $directory): array
    {
        $files = glob($directory . \DIRECTORY_SEPARATOR . '*.json');

        $snapshots = [];
        foreach (\is_array($files) ? $files : [] as $file) {
            $modified = filemtime($file);
            $snapshots[$file] = \is_int($modified) ? $modified : 0;
        }

        asort($snapshots);

        return $snapshots;
    }
}
