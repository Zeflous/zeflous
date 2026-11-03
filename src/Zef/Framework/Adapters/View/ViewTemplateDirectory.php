<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\View;

use InvalidArgumentException;
use Zef\Framework\Storage\StorageDocumentRoot;

/**
 * The native answer's templates root fold: the one seam that judges
 * where the engine's templates may live. The presentation root's
 * privacy posture is its own -- an existing, readable, non-symlink
 * directory with owner-only permissions, outside the public document
 * root -- because the templates it holds are the application's own
 * source spelled in the clear, and a world-readable or web-served
 * root would turn a rendering engine into a source-disclosure
 * boundary (the reference tree's own two-tier views doctrine: the
 * module's private views live by location and are never
 * web-readable).
 *
 * The where-outside-the-document-root judgment is the storage
 * layer's own fold ({@see StorageDocumentRoot}), reached one-way:
 * the one spelling of "a web-served tree is a disclosed tree" the
 * whole tree already shares, replayed here exactly as the queue
 * store's directory fold and the session store's directory fold
 * replay it. The geometry this fold itself owns -- existence,
 * readability, the symlink refusal, the owner-only permission mask
 * -- is spelled fresh, in the view lane's own words, because the
 * privacy question belongs to the family that reads, exactly as the
 * families that write spell their own.
 *
 * The posture deliberately does NOT demand writability: the engine
 * only ever ANSWERS includes over this root, never writes into it,
 * and a writability demand would be a posture the lane never
 * exercises -- the read root the engine composes over is read-only
 * by its own doctrine, so the very process that renders cannot also
 * tamper the tree it renders from.
 */
final class ViewTemplateDirectory
{
    /**
     * Resolves the declared directory into the canonical address the
     * engine answers includes from, refusing every shape the posture
     * above names.
     *
     * @throws InvalidArgumentException when the directory is not an
     *                                  existing readable non-symlink directory with owner-only
     *                                  permissions outside the public document root, or cannot
     *                                  be resolved
     */
    public static function resolve(string $directory): string
    {
        if ($directory === '' || is_link($directory) || !is_dir($directory) || !is_readable($directory)) {
            throw new InvalidArgumentException(
                'The view templates root must be an existing readable'
                . ' non-symlink directory.',
            );
        }

        $resolved = realpath($directory);

        if (!\is_string($resolved)) {
            throw new InvalidArgumentException(
                'The view templates root cannot be resolved.',
            );
        }

        $permissions = fileperms($resolved);

        if (!\is_int($permissions) || ($permissions & 0o077) !== 0) {
            throw new InvalidArgumentException(
                'The view templates root must have owner-only permissions.',
            );
        }

        StorageDocumentRoot::assertOutside($resolved);

        return $resolved;
    }
}
