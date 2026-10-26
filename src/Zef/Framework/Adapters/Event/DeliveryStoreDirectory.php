<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Storage\StorageDocumentRoot;

/**
 * The file-backed delivery store's own directory fold: the one
 * seam that judges where the durable entries may live. The
 * queue store's privacy posture is its own -- an existing,
 * writable, non-symlink directory with owner-only permissions,
 * outside the public document root -- because the entries it
 * holds are the application's business data spelled in the clear,
 * and a world-readable or web-served directory would turn a
 * durability promise into a disclosure.
 *
 * The where-outside-the-document-root judgment is the storage
 * layer's own fold ({@see StorageDocumentRoot}), reached one-way:
 * the one spelling of "a web-served tree is a disclosed tree" the
 * whole tree already shares, replayed here as the adapters lane's
 * first composition of a core fold. The geometry this fold itself
 * owns -- existence, writability, the symlink refusal, the
 * owner-only permission mask -- is spelled fresh, in the queue
 * store's own words, because the privacy question belongs to the
 * family that writes, exactly as the replay and JSON-log families
 * spell their own.
 */
final class DeliveryStoreDirectory
{
    /**
     * Resolves the declared directory into the canonical address
     * the store writes into, refusing every shape the posture
     * above names.
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
                'The durable delivery store must be an existing writable'
                . ' non-symlink directory.',
            );
        }

        $resolved = realpath($directory);

        if (!\is_string($resolved)) {
            throw new InvalidArgumentException(
                'The durable delivery store directory cannot be resolved.',
            );
        }

        $permissions = fileperms($resolved);

        if (!\is_int($permissions) || ($permissions & 0o077) !== 0) {
            throw new InvalidArgumentException(
                'The durable delivery store directory must have owner-only'
                . ' permissions.',
            );
        }

        StorageDocumentRoot::assertOutside($resolved);

        return $resolved;
    }
}
