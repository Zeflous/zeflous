<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Temp;

use InvalidArgumentException;

/**
 * The temp store's scan fold: which files in the lane root the
 * sweep's own walk sees -- every file, the staging dot-files the
 * write discipline strands on a crash and the cadence marker's
 * own dot-file ignored (an operator may sweep dot-files while no
 * writer runs, the same blind spot the session store's scan
 * spells), directories skipped without ending the scan (a
 * directory named like a scratch is a container the lane never
 * provisioned, never a mortality the sweep owns).
 *
 * THE SCAN CARRIES NO NAME GRAMMAR, the lane's own doctrine
 * spelled as the question it answers: the writer's landed names
 * are its own random draws, but the lane's mortality is declared
 * over the LANE, never over a name shape -- "the transient
 * scratch a process may leave behind between requests without
 * anyone mourning it" draws no distinction between the scratch
 * the writer drew and the file an operator dropped inside a lane
 * reserved for scratch, so no grammar decides whose file lives.
 * The session scan's extension test distinguishes a record from
 * a neighbour's artefact because the sessions lane is one grammar
 * among others in one tree; the temp lane IS the whole tree of
 * its own kind, so the scan's one honest question is dot-file or
 * not, directory or not.
 */
final readonly class TempScratchScan
{
    /**
     * @param string $directory the lane root the directory fold
     *                          already resolved ({@see TempStoreDirectory})
     */
    public function __construct(
        private string $directory,
    ) {
    }

    /**
     * Every scratch filename in the lane root, the staging
     * dot-files and the directories skipped.
     *
     * @return list<string> the scratch filenames, scan order
     *
     * @throws InvalidArgumentException when the lane directory
     *                                  cannot be scanned
     */
    public function scratchFilenames(): array
    {
        $scanned = scandir($this->directory);

        if ($scanned === false) {
            throw new InvalidArgumentException(
                'The temp file store directory cannot be scanned.',
            );
        }

        $filenames = [];

        foreach ($scanned as $filename) {
            if (str_starts_with($filename, '.')) {
                continue;
            }

            if (is_dir($this->directory . \DIRECTORY_SEPARATOR . $filename)) {
                continue;
            }

            $filenames[] = $filename;
        }

        return $filenames;
    }
}
