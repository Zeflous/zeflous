<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Throwable;
use Zef\Framework\Exception\NativeErrorException;
use Zef\Framework\Http\DiagnosticSeverity;

/**
 * The process boundary's native diagnostic arm: the engine's
 * error-handler body, owning the suppression contract and the
 * conversion the boundary installs it for.
 *
 * A severity masked by `error_reporting()` (the `@` operator's mask,
 * an ini directive's mask) is handed back to the engine untouched --
 * the engine's own contract, respected. The severities PHP aborts on
 * (E_USER_ERROR, E_RECOVERABLE_ERROR) are *converted* -- thrown as
 * {@see NativeErrorException} so a surrounding pipeline can map,
 * record and answer them through the typed lane. Every other severity
 * is recorded once through the diagnostic record fold and owned: one
 * diagnostic, one record, one destination, and the engine's duplicate
 * SAPI write never happens.
 */
final readonly class ProcessNativeDiagnostics
{
    /** The severities the arm converts into the typed exception lane. */
    private const int CONVERTS_TO_EXCEPTION = \E_USER_ERROR | \E_RECOVERABLE_ERROR;

    public function __construct(
        private ProcessBoundaryRecords $processBoundaryRecords,
    ) {
    }

    /**
     * Handles one native diagnostic the engine raised.
     *
     * @param int    $severity the engine's severity bits
     * @param string $message  the engine's diagnostic text
     * @param string $file     the file the diagnostic names
     * @param int    $line     the line the diagnostic names
     *
     * @return bool false hands the diagnostic back to the engine's own
     *              handling; true owns it (the engine writes nothing)
     */
    public function handle(int $severity, string $message, string $file, int $line): bool
    {
        if (($severity & error_reporting()) === 0) {
            return false;
        }

        if (($severity & self::CONVERTS_TO_EXCEPTION) !== 0) {
            throw NativeErrorException::diagnostic(
                mb_substr($message . ' in ' . $file . ' on line ' . $line, 0, 2048),
                DiagnosticSeverity::for($severity),
                $file,
                $line,
            );
        }

        try {
            $this->processBoundaryRecords->diagnostic(DiagnosticSeverity::for($severity), $message, $file, $line);
        } catch (Throwable $throwable) {
            error_log('ZEF diagnostic record failed: ' . $throwable::class);
        }

        return true;
    }
}
