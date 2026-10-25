<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Storage\StorageLane;
use Zef\Framework\Storage\StoragePaths;

/**
 * The storage map's table form: the plain listing the layout
 * inspector answers -- the resolved root on its own header line,
 * then one line per lane in the vocabulary's declaration order,
 * each carrying the lane's spelling, its canonical address and the
 * lane's own one-line purpose, closed by the lane count.
 *
 * The listing is the consistency instrument the layout exists for:
 * the same command run in the sandbox, in development and in
 * production prints the same shape, the same lanes in the same
 * order, and only the addresses differ -- the one per-environment
 * variable the doctrine carries.
 */
final readonly class StorageMapText
{
    /**
     * The whole resolved map, one line per lane.
     */
    public static function table(StoragePaths $storagePaths): string
    {
        $lanes = $storagePaths->lanes();

        $lines = [
            \sprintf('Storage map -- %d lanes under %s', \count($lanes), $storagePaths->root()),
        ];

        foreach (StorageLane::cases() as $lane) {
            $lines[] = \sprintf(
                '  %-8s %s -- %s',
                $lane->spelling(),
                $lanes[$lane->spelling()],
                $lane->purpose(),
            );
        }

        return implode("\n", $lines) . "\n";
    }
}
