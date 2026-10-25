<?php

declare(strict_types=1);

namespace Zef\Framework\Storage;

use InvalidArgumentException;

/**
 * Resolves and validates the storage root a `files/**` layout hangs
 * from: the one directory every lane joins onto, judged once here so
 * the layout's own walk stays a walk.
 *
 * The posture is the replay lane's own directory geometry
 * ({@see \Zef\Framework\Http\ReplayStorageDirectory}) generalised to
 * the whole tree: an existing, writable, non-symlink directory that
 * resolves through `realpath()` and sits OUTSIDE the public document
 * root ({@see StorageDocumentRoot}, the one spelling of that
 * judgement) -- a storage tree the web server itself can serve is a
 * disclosure waiting to happen, whatever the lane. The privacy
 * posture stays where it already lives (the replay directory's
 * owner-only requirement, the log file's private-file check): the
 * root owns WHERE the tree is, never HOW PRIVATE a lane's artifacts
 * are -- two questions, two seams, and the layout deliberately does
 * not duplicate the second.
 *
 * The root is never created for you (the standing doctrine the
 * error lane's log directory already spells): provisioning a tree
 * is deployment tooling, and a framework that silently `mkdir`s its
 * way past an operator's mount points has taken a decision that was
 * never its to take.
 */
final class StorageRoot
{
    /**
     * The canonical absolute root, or the named refusal.
     *
     * @param string $root the deployment's declared storage root
     *
     * @return string the resolved real path the lanes join onto
     *
     * @throws InvalidArgumentException when the root is empty, a
     *                                  symlink, missing, unwritable, unresolvable, or inside
     *                                  the public document root
     */
    public static function resolve(string $root): string
    {
        if ($root === '' || is_link($root) || !is_dir($root) || !is_writable($root)) {
            throw new InvalidArgumentException(
                'The storage root must be an existing writable non-symlink directory.',
            );
        }

        $resolved = realpath($root);
        if (!\is_string($resolved)) {
            throw new InvalidArgumentException('The storage root cannot be resolved.');
        }

        StorageDocumentRoot::assertOutside($resolved);

        return $resolved;
    }
}
