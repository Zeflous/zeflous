<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use InvalidArgumentException;

/** Requires an already-existing JSON log file to stay a private, regular, writable file. */
final class JsonLogFilePrivacy
{
    public static function assertExistingFileIsPrivate(string $resolvedPath): void
    {
        if (!file_exists($resolvedPath)) {
            return;
        }

        if (!is_file($resolvedPath) || is_link($resolvedPath)) {
            throw new InvalidArgumentException('The JSON log destination must be a regular non-symlink file.');
        }

        $permissions = fileperms($resolvedPath);
        if (!\is_int($permissions) || ($permissions & 0o077) !== 0 || !is_writable($resolvedPath)) {
            throw new InvalidArgumentException('The existing JSON log file must be writable with owner-only permissions.');
        }
    }
}
