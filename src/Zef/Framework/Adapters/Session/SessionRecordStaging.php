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

        // The byte-count identity the honest check carries: the bytes
        // the write laid down against the bytes the JSON string IS
        // (issue #788, finding 6 -- the first spelling compared the
        // written bytes against `mb_strlen`'s CODEPOINTS, a pairing
        // that only agreed because the encoder ASCII-escapes every
        // multibyte byte away; the day an unescaped flag joins the
        // encode, the check would refuse every multibyte record it
        // successfully wrote).
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
