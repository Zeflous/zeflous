<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Maps a native PHP diagnostic's severity bits to the PSR-3 level the
 * process boundary records it at -- the diagnostic lane's own fold of
 * {@see ErrorFaultSeverity}'s doctrine, over the engine's severities
 * instead of HTTP statuses.
 *
 * The engine hands the error handler exactly one severity bit per call,
 * and the family's standing is stable: the severities PHP aborts on are
 * critical, the recoverable fatal is an error, the mid-severity
 * diagnostics are warnings, the informational engine notices are notices,
 * and the deprecation family is information a healthy codebase wants
 * counted but never alarmed on. E_STRICT is deliberately absent: PHP 8.4
 * deprecates the constant and excludes it from E_ALL, and every condition
 * that once raised it now raises E_DEPRECATED or E_WARNING -- severities
 * the fold already maps. A severity the map does not name -- an engine
 * bit newer than this fold -- answers warning, the fail-open posture: an
 * unknown diagnostic is attention-worthy by construction, and swallowing
 * it silently would hide the very signal the boundary exists to capture.
 */
final class DiagnosticSeverity
{
    private const int FATAL = \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_CORE_WARNING
        | \E_COMPILE_ERROR | \E_COMPILE_WARNING | \E_USER_ERROR;

    private const int RECOVERABLE = \E_RECOVERABLE_ERROR;

    private const int WARNING = \E_WARNING | \E_USER_WARNING;

    private const int NOTICE = \E_NOTICE | \E_USER_NOTICE;

    private const int INFORMATIONAL = \E_DEPRECATED | \E_USER_DEPRECATED;

    /**
     * Answers the PSR-3 level the severity records at.
     *
     * @param int $severity the engine's severity bits
     *
     * @return non-empty-string one of the PSR-3 levels the JSONL lane accepts
     */
    public static function for(int $severity): string
    {
        if (($severity & self::FATAL) !== 0) {
            return 'critical';
        }

        if (($severity & self::RECOVERABLE) !== 0) {
            return 'error';
        }

        if (($severity & self::WARNING) !== 0) {
            return 'warning';
        }

        if (($severity & self::NOTICE) !== 0) {
            return 'notice';
        }

        if (($severity & self::INFORMATIONAL) !== 0) {
            return 'info';
        }

        return 'warning';
    }

    /**
     * Whether the severity belongs to the fatal family -- the severities
     * the engine aborts on, which only the shutdown moment can witness
     * (the error handler never sees them, because the engine never
     * returns from them). This is the map's own fatal arm answering the
     * one verdict the process boundary's shutdown observer exists for,
     * so the fatal family's spelling lives in this vocabulary alone.
     *
     * @param int $severity the engine's severity bits
     */
    public static function isFatalAtShutdown(int $severity): bool
    {
        return ($severity & self::FATAL) !== 0;
    }
}
