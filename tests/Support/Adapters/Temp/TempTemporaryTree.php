<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Temp;

/**
 * The temp tests' shared tree removal: the one spelling the temp
 * slice's three test classes' tear-downs answer through, so the
 * suite's own helper discipline keeps one copy instead of three --
 * a best-effort removal whose failure to remove a tree is the
 * runner's own environment, never a reason to redden an arm's
 * own verdict ({@see \Zef\Test\Support\Adapters\Uploads\UploadsTemporaryTree}
 * the spelling the uploads slice planted for its own three).
 *
 * @internal
 */
final class TempTemporaryTree
{
    /**
     * Removes a directory tree a test laid down.
     *
     * @param string $directory the tree's root the test created
     */
    public static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        foreach (\is_array($entries) ? $entries : [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . \DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                self::remove($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
