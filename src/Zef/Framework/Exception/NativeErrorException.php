<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * The native diagnostic an engine error became: the process boundary's
 * conversion signal for the error-handler severities PHP itself aborts on
 * (E_USER_ERROR and E_RECOVERABLE_ERROR), thrown so the failure crosses
 * into the typed exception lane instead of dying as a bare engine fatal.
 *
 * The conversion is the deliberate half of the boundary's error arm:
 * E_RECOVERABLE_ERROR is the engine's own "catchable fatal" contract, and
 * E_USER_ERROR is the author's explicit fatal signal -- both have
 * first-class standing in the exception family, and a throw is the only
 * spelling that lets a surrounding PSR-15 pipeline map, record and answer
 * them through the RFC 9457 contract. The remaining severities (warnings,
 * notices, deprecations) are recorded as structured diagnostics and never
 * thrown, because execution continues past them by design.
 *
 * The answer is the internal-fault posture: the boundary owns the message
 * (the engine's own diagnostic text, bounded and redacted at the record
 * seam), so it answers through the typed family's public-message posture
 * like {@see InternalException} does -- a 500 nobody downstream chose the
 * words for, but one whose origin the details name honestly.
 */
final class NativeErrorException extends ZefException implements CarriesHttpStatus
{
    /**
     * The one construction site: the engine's diagnostic, bounded before it
     * crosses into the typed lane.
     *
     * @param non-empty-string $message  the engine's diagnostic text, already
     *                                   bounded by the caller
     * @param string           $severity the PSR-3 level the diagnostic mapped to
     * @param string           $file     the file the diagnostic names
     * @param int              $line     the line the diagnostic names
     */
    public static function diagnostic(string $message, string $severity, string $file, int $line): self
    {
        return new self(
            $message,
            0,
            null,
            'NATIVE_ERROR',
            [
                'severity' => $severity,
                'file' => mb_substr($file, 0, 2048),
                'line' => $line,
            ],
        );
    }

    #[Override]
    public function httpStatus(): int
    {
        return 500;
    }
}
