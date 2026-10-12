<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The layer's scoped engine-call seam for calls whose failure must keep
 * the reference implementation's message shape: the diagnostic is captured
 * and answered alongside the call's result instead of being thrown with a
 * message of this seam's own.
 *
 * This is the capture-style twin of {@see StreamOpener} -- that seam throws
 * from inside its handler because its message format owns the diagnostic,
 * while the upload move's byte-identical legacy messages append the engine
 * warning to their own text, so the warning has to come back as a value.
 * Only warning-grade diagnostics are captured; lower-severity noise must
 * not overwrite the real cause.
 */
final class ScopedDiagnostics
{
    /**
     * Opens the URI under the scoped handler.
     *
     * @param non-empty-string $uri
     * @param non-empty-string $mode
     *
     * @return array{false|resource, null|string} the fopen() result and the
     *                                            captured diagnostic, when
     *                                            the engine emitted one
     */
    public static function fopen(string $uri, string $mode): array
    {
        [$result, $warning] = self::capture(static fn (): mixed => fopen($uri, $mode));

        return [$result, $warning];
    }

    /**
     * Renames one path onto another under the scoped handler.
     *
     * @param non-empty-string $from
     * @param non-empty-string $to
     *
     * @return array{bool, null|string} the rename() result and the captured
     *                                  diagnostic, when the engine emitted one
     */
    public static function rename(string $from, string $to): array
    {
        [$result, $warning] = self::capture(static fn (): bool => rename($from, $to));

        return [$result, $warning];
    }

    /**
     * Runs the operation under the scoped handler.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return array{T, null|string}
     */
    private static function capture(callable $operation): array
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            if (($severity & (\E_WARNING | \E_NOTICE)) !== 0) {
                $warning = $message;
            }

            return true;
        });

        try {
            $result = $operation();
        } finally {
            restore_error_handler();
        }

        return [$result, $warning];
    }
}
