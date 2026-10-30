<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * The session store's scan fold: which files in the store root the
 * store's own walks see -- every record file, the staging
 * dot-files the write discipline strands on a crash ignored (an
 * operator may sweep dot-files while no writer runs), directories
 * skipped without ending the scan, the extension's absence the
 * difference between a record and a neighbour's artefact.
 *
 * The scan is the sweep's own question ({@see SessionSweep}) and
 * the layout's naming ({@see SessionRecordFiles}) stays the
 * extension's one spelling: the two folds answer two questions --
 * which file one identifier IS, and which files the store HOLDS.
 */
final readonly class SessionRecordScan
{
    /**
     * @param string $directory the store root the directory fold
     *                          already resolved ({@see SessionStoreDirectory})
     */
    public function __construct(
        private string $directory,
    ) {
    }

    /**
     * Every record filename in the store root, the staging
     * dot-files and the directories skipped.
     *
     * @return list<string> the record filenames, scan order
     *
     * @throws InvalidArgumentException when the store directory
     *                                  cannot be scanned
     */
    public function recordFilenames(): array
    {
        $scanned = scandir($this->directory);

        if ($scanned === false) {
            throw new InvalidArgumentException(
                'The session file store directory cannot be scanned.',
            );
        }

        $filenames = [];

        foreach ($scanned as $filename) {
            if (str_starts_with($filename, '.') || !str_ends_with($filename, SessionRecordFiles::EXTENSION)) {
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
