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
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $resolvedDocumentRoot = \is_string($documentRoot) && $documentRoot !== ''
            ? realpath($documentRoot)
            : false;

        if (
            \is_string($documentRoot)
            && $documentRoot !== ''
            && !\is_string($resolvedDocumentRoot)
        ) {
            throw new InvalidArgumentException('The public document root cannot be resolved safely.');
        }

        if (
            \is_string($resolvedDocumentRoot)
            && str_starts_with(
                $resolvedPath . \DIRECTORY_SEPARATOR,
                mb_rtrim($resolvedDocumentRoot, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR,
            )
        ) {
            throw new InvalidArgumentException('The JSON log destination must be outside the public document root.');
        }

        if (file_exists($resolvedPath)) {
            if (!is_file($resolvedPath) || is_link($resolvedPath)) {
                throw new InvalidArgumentException('The JSON log destination must be a regular non-symlink file.');
            }

            $permissions = fileperms($resolvedPath);
            if (!\is_int($permissions) || ($permissions & 0o077) !== 0 || !is_writable($resolvedPath)) {
                throw new InvalidArgumentException('The existing JSON log file must be writable with owner-only permissions.');
            }
        }

        return $resolvedPath;
    }
}
