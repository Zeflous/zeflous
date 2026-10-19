<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use InvalidArgumentException;

/** Keeps the JSON log destination outside the public document root, failing closed on an unresolvable root. */
final class JsonLogDocumentRoot
{
    public static function assertDestinationIsOutside(string $resolvedPath): void
    {
        $documentRoot = self::documentRoot();
        $resolvedDocumentRoot = $documentRoot !== ''
            ? realpath($documentRoot)
            : false;

        if ($documentRoot !== '' && !\is_string($resolvedDocumentRoot)) {
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

    /**
     * Answers the SAPI document root, or the empty string when the runtime
     * carries none (CLI workers) or a corrupt entry -- the whole-array @var
     * seam the RequestFactory bag reads use (the KDD-H10 idiom), so the
     * analyser sees the bag's entries as the mixed they are at runtime.
     */
    private static function documentRoot(): string
    {
        /** @var array<string, mixed> $server the engine's process state */
        $server = $_SERVER;

        return self::rootOf($server['DOCUMENT_ROOT'] ?? '');
    }

    /** @param mixed $root the raw document-root entry, null when the key is absent */
    private static function rootOf(mixed $root): string
    {
        return \is_string($root) ? $root : '';
    }
}
