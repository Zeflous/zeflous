<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The fault recorder: the PSR-3 seam where a request's server-tier
 * failures are reported.
 *
 * A 4xx answer is the client's own refusal, not a fault -- nothing is
 * recorded (the observability lane's level policy will add the
 * 4xx-as-warnings tier when it lands, here, without touching the
 * mapper). A server-tier failure is reported with its message already
 * redacted ({@see RedactedMessage}) plus the request facts a triage
 * needs. A wired logger that itself fails is the last resort: the
 * failing class's name goes straight to the SAPI error log, because
 * there is no lower fallback left to try; an absent logger is silence,
 * not an error.
 *
 * Extracted from the error mapping middleware (the error-handling lane,
 * ROADMAP.md "Error Handling System" phase 2): mapping an answer and
 * recording a fault are separate responsibilities, and the level policy
 * of phase 3 grows here without the mapper changing shape.
 */
final readonly class ErrorFaultRecorder
{
    /** The floor of the server-error class: the tiers a handler fault reaches are recorded. */
    private const int SERVER_ERROR = 500;

    public function __construct(private ?LoggerInterface $logger = null)
    {
    }

    /**
     * Reports a failure's fault half, when it has one: the server-tier
     * statuses are recorded with redacted context, the client tiers are
     * the caller's own refusal and stay unrecorded.
     *
     * @param int $status the status the failure maps to
     */
    public function record(
        Throwable $exception,
        int $status,
        string $correlationId,
        ServerRequestInterface $serverRequest,
    ): void {
        if ($status < self::SERVER_ERROR) {
            return;
        }

        try {
            $this->logger?->error('Unhandled application exception', [
                'exception' => $exception,
                'exception.message' => RedactedMessage::of($exception->getMessage()),
                'request_id' => $correlationId,
                'method' => $serverRequest->getMethod(),
                'path' => $serverRequest->getUri()->getPath(),
            ]);
        } catch (Throwable $throwable) {
            error_log('ZEF logging failure: ' . $throwable::class);
        }
    }
}
