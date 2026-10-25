<?php

declare(strict_types=1);

namespace Zef\Framework\Storage;

use InvalidArgumentException;

/**
 * The `files/**` route map's resolution walk: one declared root in,
 * the whole validated lane map out -- the single composition seam
 * every future consumer (the error lane's log address, the
 * configuration cache, the session file driver) resolves through,
 * so the tree's shape is answered once and consistently instead of
 * re-derived per family.
 *
 * The walk is deliberately two folds and a composition: the root's
 * own geometry ({@see StorageRoot}) judged once, then every lane's
 * directory ({@see StorageLaneDirectory}) judged in the vocabulary's
 * declaration order -- a lane that fails names itself, and the walk
 * stops at the first refusal (fail-closed, never a partial map:
 * a host told "four of five lanes resolved" would boot half a tree
 * and learn which lane from an unrelated write failure later).
 *
 * The layout answers the SAME map wherever it runs -- sandbox,
 * development, production -- because the lanes are relative to the
 * one root the deployment declares: the only per-environment
 * variable in the whole doctrine is that root, and the inspector
 * ({@see \Zef\Test\Support\Tooling\StorageMapText}) exists so an
 * operator can ask any environment the same question and read the
 * same-shaped answer.
 */
final class StorageLayout
{
    /**
     * The whole validated map over a declared root.
     *
     * @param string $root the deployment's declared storage root
     *
     * @return StoragePaths every lane's canonical address, the
     *                      vocabulary's declaration order held
     *
     * @throws InvalidArgumentException when the root or any lane's
     *                                  directory refuses -- the refusing seam named in the
     *                                  message
     */
    public static function resolve(string $root): StoragePaths
    {
        $resolvedRoot = StorageRoot::resolve($root);

        $paths = [];
        foreach (StorageLane::cases() as $lane) {
            $paths[$lane->spelling()] = StorageLaneDirectory::resolve($resolvedRoot, $lane);
        }

        return new StoragePaths($resolvedRoot, $paths);
    }
}
