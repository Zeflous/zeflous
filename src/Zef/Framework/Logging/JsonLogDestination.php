<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use InvalidArgumentException;

/** Resolves and validates a private JSONL log destination. */
final class JsonLogDestination
{
    public static function resolve(string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || is_dir($path) || is_link($path)) {
            throw new InvalidArgumentException('The JSON log path must be a non-symlink file in an existing directory.');
        }

        $directory = realpath(\dirname($path));
        $filename = basename($path);
        if (!\is_string($directory) || $filename === '' || $filename === '.' || $filename === '..') {
            throw new InvalidArgumentException('The JSON log path must be a file in an existing directory.');
        }

        $resolvedPath = $directory . \DIRECTORY_SEPARATOR . $filename;
        JsonLogDocumentRoot::assertDestinationIsOutside($resolvedPath);
        JsonLogFilePrivacy::assertExistingFileIsPrivate($resolvedPath);

        return $resolvedPath;
    }
}
