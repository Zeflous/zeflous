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
 * the uploads family's own words, as the three guards the fold's
 * own answer spells (the depth-split discipline the tree's folds
 * keep under the architecture gates' ceilings): the geometry
 * guard, the canonical address and the permission mask each own
 * one question, one refusal, one seam -- the same posture the
 * sister lanes carry, in the family's own spelling (KDD-US10).
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
        self::refuseUnwritableGeometry($directory);

        $resolved = self::canonicalAddress($directory);

        self::refusePermissiveMask($resolved);

        StorageDocumentRoot::assertOutside($resolved);

        return $resolved;
    }

    /**
     * The geometry guard: an existing, writable, non-symlink
     * directory -- the writer's own landing surface.
     *
     * @param string $directory the declared or derived uploads lane
     *                          address
     *
     * @throws InvalidArgumentException when the directory is empty,
     *                                  a symlink, missing, or unwritable
     */
    private static function refuseUnwritableGeometry(string $directory): void
    {
        if ($directory === '' || is_link($directory) || !is_dir($directory) || !is_writable($directory)) {
            throw new InvalidArgumentException(
                'The uploads file store must be an existing writable'
                . ' non-symlink directory.',
            );
        }
    }

    /**
     * The canonical address: the resolved real path the landing's
     * own name joins onto, refused when the engine cannot resolve
     * it.
     *
     * @param string $directory the declared or derived uploads lane
     *                          address
     *
     * @throws InvalidArgumentException when the directory cannot be
     *                                  resolved
     */
    private static function canonicalAddress(string $directory): string
    {
        $resolved = realpath($directory);

        if (!\is_string($resolved)) {
            throw new InvalidArgumentException(
                'The uploads file store directory cannot be resolved.',
            );
        }

        return $resolved;
    }

    /**
     * The permission mask guard: owner-only, the posture the lane's
     * inbound artefacts demand.
     *
     * @param string $resolved the resolved real path being judged
     *
     * @throws InvalidArgumentException when the mask cannot be read
     *                                  or carries a group or world bit
     */
    private static function refusePermissiveMask(string $resolved): void
    {
        $permissions = fileperms($resolved);

        if (!\is_int($permissions) || ($permissions & 0o077) !== 0) {
            throw new InvalidArgumentException(
                'The uploads file store directory must have owner-only'
                . ' permissions.',
            );
        }
    }
}
