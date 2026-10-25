<?php

declare(strict_types=1);

namespace Zef\Framework\Storage;

use InvalidArgumentException;

/**
 * Resolves and validates one lane's directory under the storage
 * root: the per-lane fold the layout's walk composes, so the walk
 * itself never spells a filesystem judgement.
 *
 * A lane's directory is judged exactly the way the root is --
 * existing, a directory, not a symlink, writable, resolvable --
 * with the lane NAMED in every refusal: an operator told "the
 * 'uploads' lane is not writable" fixes one directory, an operator
 * told "the layout is invalid" guesses. The permission bits stay
 * the lane's own consumer's judgement (the replay lane's
 * owner-only requirement, the log file's private-file check): this
 * fold answers whether the lane can be written at all, never
 * whether what lands in it is private enough -- the boundary the
 * route map itself draws ({@see StorageRoot}).
 *
 * The lane's spelling joins the root through the platform's own
 * separator, and the answer is the resolved real path, so a host
 * that declares the root through a symlinked parent still reads
 * canonical lane addresses.
 */
final class StorageLaneDirectory
{
    /**
     * The lane's canonical absolute directory, or the named refusal.
     *
     * @param string      $root        the resolved storage root ({@see StorageRoot::resolve()})
     * @param StorageLane $storageLane the lane whose directory is resolved
     *
     * @return string the resolved real path the lane's writers use
     *
     * @throws InvalidArgumentException when the lane's directory is
     *                                  missing, a plain file, a symlink, unwritable, or
     *                                  unresolvable -- the lane named in the message
     */
    public static function resolve(string $root, StorageLane $storageLane): string
    {
        $joined = $root . \DIRECTORY_SEPARATOR . $storageLane->spelling();

        if (is_link($joined) || !is_dir($joined) || !is_writable($joined)) {
            throw new InvalidArgumentException(
                "The storage lane '" . $storageLane->spelling()
                . "' must be an existing writable non-symlink directory.",
            );
        }

        $resolved = realpath($joined);
        if (!\is_string($resolved)) {
            throw new InvalidArgumentException(
                "The storage lane '" . $storageLane->spelling() . "' cannot be resolved.",
            );
        }

        return $resolved;
    }
}
