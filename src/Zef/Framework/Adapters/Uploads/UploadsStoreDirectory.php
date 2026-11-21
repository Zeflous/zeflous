<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Uploads;

use InvalidArgumentException;
use Zef\Framework\Storage\StorageDocumentRoot;

/**
 * The file-backed uploads store's own directory fold: the one seam
 * that judges where the landed uploads may live. The uploads
 * store's privacy posture is its own -- an existing, writable,
 * non-symlink directory with owner-only permissions, outside the
 * public document root -- because the files it lands are the
 * host's own inbound artefacts, and a world-readable or
 * web-served directory would turn a validated landing into a
 * disclosure (the same posture the durable delivery store's and
 * the session file store's own directory folds spell for their
 * artefacts, replayed here in the uploads family's own words --
 * the privacy question belongs to the family that writes).
 *
 * The where-outside-the-document-root judgment is the storage
 * layer's own fold ({@see StorageDocumentRoot}), reached one-way:
 * the one spelling of "a web-served tree is a disclosed tree" the
 * whole tree already shares, composed here exactly as the
 * delivery and session directory folds compose it. The geometry
 * this fold itself owns -- existence, writability, the symlink
 * refusal, the owner-only permission mask -- is spelled fresh, in
 * the uploads family's own words, for the same reason: the
 * uploads lane is the one Partial area the priorities table
 * spells as a production attack surface, so its first writer
 * lands the strictest posture the sister lanes already carry.
 */
final class UploadsStoreDirectory
{
    /**
     * Resolves the declared directory into the canonical address
     * the writer lands into, refusing every shape the posture
     * above names.
     *
     * @param string $directory the declared or derived uploads lane
     *                          address
     *
     * @throws InvalidArgumentException when the directory is not
     *                                  an existing writable non-symlink directory with
     *                                  owner-only permissions outside the public document
     *                                  root, or cannot be resolved
     */
    public static function resolve(string $directory): string
    {
        if ($directory === '' || is_link($directory) || !is_dir($directory) || !is_writable($directory)) {
            throw new InvalidArgumentException(
                'The uploads file store must be an existing writable'
                . ' non-symlink directory.',
            );
        }

        $resolved = realpath($directory);

        if (!\is_string($resolved)) {
            throw new InvalidArgumentException(
                'The uploads file store directory cannot be resolved.',
            );
        }

        $permissions = fileperms($resolved);

        if (!\is_int($permissions) || ($permissions & 0o077) !== 0) {
            throw new InvalidArgumentException(
                'The uploads file store directory must have owner-only'
                . ' permissions.',
            );
        }

        StorageDocumentRoot::assertOutside($resolved);

        return $resolved;
    }
}
