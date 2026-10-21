<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Http\DiagnosticSeverity;

/**
 * The process boundary's fatal family: the shutdown arm's witness
 * grammar -- the fatal severities only {@see ProcessErrorBoundary}'s
 * shutdown arm can observe, and the shape those observed errors must
 * carry before the arm answers them.
 *
 * The engine's `error_get_last()` answer is an untyped map: the type
 * bits may be absent or foreign, the message, file and line may be
 * absent or mistyped, and a non-fatal diagnostic (a warning the error
 * arm already owned, a deprecation the engine already answered) rides
 * the same channel. The fold refuses everything but the fatal family
 * ({@see DiagnosticSeverity::isFatalAtShutdown()}'s verdict -- the
 * engine-severity vocabulary's own mask, one source) with a
 * well-formed shape -- the one witness the shutdown arm exists for --
 * and carries the witnessed facts the arm's record and answer both
 * read.
 */
final readonly class ProcessBoundaryFatals
{
    /** The PSR-3 level the fatal family records at. */
    public const string SEVERITY = 'critical';

    private function __construct(
        public string $message,
        public string $file,
        public int $line,
    ) {
    }

    /**
     * Witnesses the engine's last error, answering the fatal the
     * shutdown arm exists for and refusing everything else -- the null
     * verdict being the caller's own (an engine that never raised
     * anything answers no last error at all).
     *
     * @param array<string, mixed> $lastError the engine's own last-error map
     */
    public static function witness(array $lastError): ?self
    {
        $type = $lastError['type'] ?? null;
        $message = $lastError['message'] ?? null;
        $file = $lastError['file'] ?? null;
        $line = $lastError['line'] ?? null;

        if (
            !\is_int($type)
            || !DiagnosticSeverity::isFatalAtShutdown($type)
            || !\is_string($message)
            || !\is_string($file)
            || !\is_int($line)
        ) {
            return null;
        }

        return new self($message, $file, $line);
    }
}
