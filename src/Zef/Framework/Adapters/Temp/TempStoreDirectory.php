<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Temp;

use InvalidArgumentException;
use Zef\Framework\Storage\StorageDocumentRoot;

/**
 * The file-backed temp store's own directory fold: the one seam that
 * judges where the lane's mortal scratch may live. The temp
 * store's privacy posture is its own -- an existing, writable,
 * non-symlink directory with owner-only permissions, outside the
 * public document root -- because the scratch the lane lands is the
 * only artefact family the tree writes with NO judgment of its own:
 * the uploads lane validates a type, the session lane encodes a
 * shape, the delivery store audits a record, and the temp lane
 * lands whatever bytes the host hands it, unvalidated by design --
 * so a world-readable or web-served directory would turn the
 * lane's own word, "nobody mourns it", into "everybody reads it"
 * (the same posture the durable delivery store's, the session
 * file store's and the uploads store's own directory folds spell
 * for their artefacts, replayed here in the temp family's own
 * words -- the privacy question belongs to the family that
 * writes).
 *
 * The where-outside-the-document-root judgment is the storage
 * layer's own fold ({@see StorageDocumentRoot}), reached one-way:
 * the one spelling of "a web-served tree is a disclosed tree" the
 * whole tree already shares, composed here exactly as the
 * delivery, session and uploads directory folds compose it. The
 * geometry this fold itself owns -- existence, writability, the
 * symlink refusal, the owner-only permission mask -- is spelled
 * fresh, in the temp family's own words, as the three guards the
 * fold's own answer spells (the depth-split discipline the tree's
 * folds keep under the architecture gates' ceilings): the geometry
 * guard, the canonical address and the permission mask each own
 * one question, one refusal, one seam.
 */
final class TempStoreDirectory
{
    /**
     * Resolves the declared directory into the canonical address
     * the writer lands into, refusing every shape the posture
     * above names.
     *
     * @param string $directory the declared or derived temp lane
     *                          address
     *
     * @throws InvalidArgumentException when the directory is not
     *                                  an existing writable non-symlink directory with
     *                                  owner-only permissions outside the public document
     *                                  root, or cannot be resolved
     */
    public static function resolve(string $directory): string
    {
        self::refuseUnlandableLane($directory);

        $resolved = self::resolvedLaneAddress($directory);

        self::refuseASharedLane($resolved);

        StorageDocumentRoot::assertOutside($resolved);

        return $resolved;
    }

    /**
     * The geometry guard: an existing, writable, non-symlink
     * directory -- the writer's own landing surface, judged as the
     * family's own positive spelling (the landable lane), the
     * refusal the one honest answer to a lane the process cannot
     * land into.
     *
     * @param string $directory the declared or derived temp lane
     *                          address
     *
     * @throws InvalidArgumentException when the directory is empty,
     *                                  a symlink, missing, or unwritable
     */
    private static function refuseUnlandableLane(string $directory): void
    {
        $landable = $directory !== ''
            && !is_link($directory)
            && is_dir($directory)
            && is_writable($directory);

        if (!$landable) {
            throw new InvalidArgumentException(
                'The temp file store must be an existing writable'
                . ' non-symlink directory.',
            );
        }
    }

    /**
     * The canonical address: the resolved real path the landing's
     * own name joins onto, refused when the engine cannot resolve
     * it.
     *
     * @param string $directory the declared or derived temp lane
     *                          address
     *
     * @throws InvalidArgumentException when the directory cannot be
     *                                  resolved
     */
    private static function resolvedLaneAddress(string $directory): string
    {
        $resolved = realpath($directory);

        if ($resolved === false) {
            throw new InvalidArgumentException(
                'The temp file store directory cannot be resolved.',
            );
        }

        return $resolved;
    }

    /**
     * The permission mask guard: owner-only, the posture the lane's
     * unjudged scratch demands, refused as the family's own shared
     * lane (a mask the group or world can read).
     *
     * @param string $resolved the resolved real path being judged
     *
     * @throws InvalidArgumentException when the mask cannot be read
     *                                  or carries a group or world bit
     */
    private static function refuseASharedLane(string $resolved): void
    {
        $permissions = fileperms($resolved);

        if ($permissions === false || ($permissions & 0o077) !== 0) {
            throw new InvalidArgumentException(
                'The temp file store directory must have owner-only'
                . ' permissions.',
            );
        }
    }
}
