<?php

declare(strict_types=1);

namespace Zef\Framework\Storage;

use InvalidArgumentException;

/**
 * Keeps a resolved storage address outside the public document root,
 * failing closed on an unresolvable root: the document-root geometry
 * the storage root's own guard composes -- the JSON log destination's
 * fold ({@see \Zef\Framework\Logging\JsonLogDocumentRoot}) replayed
 * as the layout's own single-question seam, so the whole `files/**`
 * tree shares one spelling of the "a web-served tree is a disclosed
 * tree" judgement.
 *
 * The geometry is one comparison: the resolved address with a
 * trailing separator starts with the document root plus its own
 * separator -- equal and inside answer true, a sibling whose name
 * merely extends the root's answers false (`public-root-evil` is
 * not inside `public-root`).
 */
final class StorageDocumentRoot
{
    /**
     * Asserts the resolved address sits outside the public document
     * root, refusing an unresolvable declared root on the way.
     *
     * @param string $resolved the resolved real path being judged
     *
     * @throws InvalidArgumentException when a declared document root
     *                                  cannot be resolved, or the address is the document
     *                                  root or sits inside it
     */
    public static function assertOutside(string $resolved): void
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
                $resolved . \DIRECTORY_SEPARATOR,
                mb_rtrim($resolvedDocumentRoot, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR,
            )
        ) {
            throw new InvalidArgumentException('The storage root must be outside the public document root.');
        }
    }

    /**
     * Answers the SAPI document root, or the empty string when the
     * runtime carries none (CLI workers) or a corrupt entry -- the
     * whole-array @var seam the request factory bag and both
     * document-root folds read through, so the analyser sees the
     * bag's entries as the mixed they are at runtime.
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
