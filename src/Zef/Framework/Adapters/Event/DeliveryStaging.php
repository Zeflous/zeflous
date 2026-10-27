<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;

/**
 * The record-file write discipline: how one record file becomes a
 * file at all -- staged beside its destination under a leading-dot
 * name, secured owner-only, then renamed into place with one
 * {@see rename()}, the same discipline the compiled route cache
 * already spells, so a reader never observes a half-written
 * record. The staging name carries a leading dot on purpose: a
 * process that dies between staging and rename strands a dot-file
 * the scans ignore (an operator may sweep dot-files while no
 * writer runs), never a partial record a later drain would have
 * to refuse.
 *
 * The fold owns the physical write only; which file, under what
 * name, in which lane, is the layout question
 * {@see DeliveryFiles} owns.
 */
final class DeliveryStaging
{
    /**
     * Writes one record file atomically into its destination.
     *
     * @throws InvalidArgumentException when the record cannot be
     *                                  written, secured or renamed into place
     */
    public function write(string $destination, string $json): void
    {
        $staging = \dirname($destination) . \DIRECTORY_SEPARATOR . '.' . basename($destination);

        if (file_put_contents($staging, $json, \LOCK_EX) !== mb_strlen($json)) {
            throw new InvalidArgumentException(
                "The delivery store cannot write the record '" . basename($destination) . "'.",
            );
        }

        if (!chmod($staging, 0o600)) {
            throw new InvalidArgumentException(
                "The delivery store cannot secure the record '" . basename($destination) . "'.",
            );
        }

        if (!rename($staging, $destination)) {
            throw new InvalidArgumentException(
                "The delivery store cannot finalise the record '" . basename($destination) . "'.",
            );
        }
    }
}
