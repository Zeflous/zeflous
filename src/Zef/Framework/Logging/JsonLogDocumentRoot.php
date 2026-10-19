<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use InvalidArgumentException;

/** Keeps the JSON log destination outside the public document root, failing closed on an unresolvable root. */
final class JsonLogDocumentRoot
{
    public static function assertDestinationIsOutside(string $resolvedPath): void
    {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $resolvedDocumentRoot = \is_string($documentRoot) && $documentRoot !== ''
            ? realpath($documentRoot)
            : false;

        if (\is_string($documentRoot) && $documentRoot !== '' && !\is_string($resolvedDocumentRoot)) {
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
    }
}
