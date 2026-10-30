<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;
use Zef\Framework\Storage\StorageDocumentRoot;

/**
 * The file-backed session store's own directory fold: the one seam
 * that judges where the session record files may live. The session
 * store's privacy posture is its own -- an existing, writable,
 * non-symlink directory with owner-only permissions, outside the
 * public document root -- because the records it holds are the
 * users' own session bags spelled in the clear, and a
 * world-readable or web-served directory would turn a persistence
 * promise into a disclosure (the same posture the durable delivery
 * store's own directory fold spells for the application's business
 * data, replayed here in the session family's own words -- the
 * privacy question belongs to the family that writes).
 *
 * The where-outside-the-document-root judgment is the storage
 * layer's own fold ({@see StorageDocumentRoot}), reached one-way:
 * the one spelling of "a web-served tree is a disclosed tree" the
 * whole tree already shares, composed here exactly as the delivery
 * store's directory fold composes it. The geometry this fold
 * itself owns -- existence, writability, the symlink refusal, the
 * owner-only permission mask -- is spelled fresh, in the session
 * family's own words, for the same reason.
 */
final class SessionStoreDirectory
{
    /**
     * Resolves the declared directory into the canonical address the
     * session store writes into, refusing every shape the posture
     * above names.
     *
     * @throws InvalidArgumentException when the directory is not an
     *                                  existing writable non-symlink directory with owner-only
     *                                  permissions outside the public document root, or cannot be
     *                                  resolved
     */
    public static function resolve(string $directory): string
    {
        if ($directory === '' || is_link($directory) || !is_dir($directory) || !is_writable($directory)) {
            throw new InvalidArgumentException(
                'The session file store must be an existing writable non-symlink directory.',
            );
        }

        $resolved = realpath($directory);

        if (!\is_string($resolved)) {
            throw new InvalidArgumentException(
                'The session file store directory cannot be resolved.',
            );
        }

        $permissions = fileperms($resolved);

        if (!\is_int($permissions) || ($permissions & 0o077) !== 0) {
            throw new InvalidArgumentException(
                'The session file store directory must have owner-only permissions.',
            );
        }

        StorageDocumentRoot::assertOutside($resolved);

        return $resolved;
    }
}
