<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds the bounded, redacted facts one native diagnostic record carries
 * -- the diagnostic lane's twin of {@see ErrorFaultContextFactory}, over
 * the engine's severities instead of a throwable.
 *
 * The bounds and the privacy posture are the exception lane's own: the
 * message is credential-redacted and capped at 2048 characters, the file
 * and path facts carry the same cap, and the request facts are exactly
 * method and path -- user identifiers, IP addresses, user-agent strings,
 * query strings and request bodies are deliberately excluded
 * (`docs/error-handling.md`, privacy-by-default). A trace id is
 * deliberately absent too: W3C trace-context resolution belongs to the
 * exception lane, where a throwable's own trace id can travel; a native
 * diagnostic correlates through the request id alone, which is the claim
 * the response the request is about to answer carries either way.
 */
final class DiagnosticContextFactory
{
    private const int FACT_BOUND = 2048;

    /**
     * @param non-empty-string       $level         the PSR-3 level the diagnostic
     *                                              records at ({@see DiagnosticSeverity})
     * @param string                 $message       the engine's diagnostic text
     * @param string                 $file          the file the diagnostic names
     * @param int                    $line          the line the diagnostic names
     * @param string                 $correlationId the request the diagnostic belongs to
     * @param ServerRequestInterface $serverRequest the request in flight
     *
     * @return array<string, mixed> the record's context
     */
    public static function create(
        string $level,
        string $message,
        string $file,
        int $line,
        string $correlationId,
        ServerRequestInterface $serverRequest,
    ): array {
        return [
            'diagnostic.severity' => $level,
            'diagnostic.message' => RedactedMessage::of(mb_substr($message, 0, self::FACT_BOUND)),
            'diagnostic.file' => mb_substr($file, 0, self::FACT_BOUND),
            'diagnostic.line' => $line,
            'request_id' => $correlationId,
            'method' => $serverRequest->getMethod(),
            'path' => mb_substr($serverRequest->getUri()->getPath(), 0, self::FACT_BOUND),
        ];
    }
}
