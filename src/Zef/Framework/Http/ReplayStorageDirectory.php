<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use InvalidArgumentException;

/** Validates the private directory used for replay snapshots. */
final class ReplayStorageDirectory
{
    public static function resolve(string $directory): string
    {
        if ($directory === '' || is_link($directory) || !is_dir($directory) || !is_writable($directory)) {
            throw new InvalidArgumentException('Replay storage must be an existing writable non-symlink directory.');
        }

        $resolved = realpath($directory);
        if (!\is_string($resolved)) {
            throw new InvalidArgumentException('Replay storage directory cannot be resolved.');
        }

        $permissions = fileperms($resolved);
        if (!\is_int($permissions) || ($permissions & 0o077) !== 0) {
            throw new InvalidArgumentException('Replay storage directory must have owner-only permissions.');
        }

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
            && ($resolved === $resolvedDocumentRoot
                || str_starts_with($resolved, mb_rtrim($resolvedDocumentRoot, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR))
        ) {
            throw new InvalidArgumentException('Replay storage must be outside the public document root.');
        }

        return $resolved;
    }
}
