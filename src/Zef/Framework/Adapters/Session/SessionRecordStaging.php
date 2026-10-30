<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * The session record's write discipline: how one record file
 * becomes a file at all -- staged beside its destination under a
 * leading-dot name, secured owner-only, then renamed into place
 * with one {@see rename()}, the same discipline the compiled route
 * cache and the durable delivery store's records already spell, so
 * a load never observes a half-written bag. The staging name
 * carries a leading dot on purpose: a process that dies between
 * staging and rename strands a dot-file the scans ignore (operator
 * sweep material, never a partial record a later load would have
 * to refuse).
 *
 * The fold owns the physical write only; which file, under what
 * name, and what the file carries are the questions {@see
 * SessionRecordFiles} and {@see SessionRecordEncoder} own.
 */
final class SessionRecordStaging
{
    /**
     * Writes one record file atomically into its destination.
     *
     * @throws InvalidArgumentException when the record cannot be
     *                                  written, secured or renamed into place
     */
    public function write(string $destination, string $json): void
    {
        $record = basename($destination);
        $staging = \dirname($destination) . \DIRECTORY_SEPARATOR . '.' . $record;

        if (file_put_contents($staging, $json, \LOCK_EX) !== mb_strlen($json)) {
            throw new InvalidArgumentException(
                "The session file store cannot write the record '{$record}'.",
            );
        }

        if (!chmod($staging, 0o600)) {
            throw new InvalidArgumentException(
                "The session file store cannot secure the record '{$record}'.",
            );
        }

        if (!rename($staging, $destination)) {
            throw new InvalidArgumentException(
                "The session file store cannot finalise the record '{$record}'.",
            );
        }
    }
}
