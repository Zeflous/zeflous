<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Loader;

use FilesystemIterator;
use Throwable;
use Zef\Framework\Config\ConfigException;

/**
 * A configuration directory's own inventory: every direct child's
 * basename, the executable allowlist the boot seam hands to the file
 * loader.
 *
 * The inventory is deliberately unfiltered -- extension, hidden
 * entries, every narrow filter stays where it already lives, in the
 * configuration layer's own directory adapter -- because the allowlist
 * is the directory's *declared* inventory, not the loader's judgement
 * of it: a child the adapter skips is never executed, and a child the
 * adapter would load is always on the list. The walk is deterministic
 * (the filesystem iterator's own order) and judges nothing but the
 * directory verdict itself: anything that is not a directory is a
 * configuration failure naming the path, never a silent empty
 * inventory -- and a directory that vanishes or turns unreadable
 * between the verdict and the walk's first step answers the same
 * family's exception (the iterator's own foreign throwable folded
 * into the {@see ConfigException} the loader's fail-closed contract
 * already speaks), never an exception the family never owned.
 */
final readonly class DirectoryInventory
{
    /**
     * Answers every direct child's basename under the directory.
     *
     * @param non-empty-string $directory the directory to inventory
     *
     * @return list<non-empty-string> the declared child names, in the
     *                                iterator's own order
     */
    public static function names(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new ConfigException(
                \sprintf('The application configuration directory "%s" is not a directory.', $directory),
            );
        }

        $names = [];

        try {
            foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
                $name = basename((string) $file);

                \assert($name !== '');

                $names[] = $name;
            }
        } catch (Throwable $throwable) {
            throw new ConfigException(
                \sprintf(
                    'The application configuration directory "%s" could not be inventoried: '
                    . 'it vanished or turned unreadable mid-walk.',
                    $directory,
                ),
                0,
                $throwable,
            );
        }

        return $names;
    }
}
