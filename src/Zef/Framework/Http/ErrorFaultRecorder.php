<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Zef\Framework\Exception\ZefException;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The fault recorder: the PSR-3 seam where a request's server-tier
 * failures are reported.
 *
 * Every failed request is recorded at the policy level: 4xx as warning,
 * typed 5xx as error and unexpected throwables as critical. The message
 * and user-agent are bounded/redacted; request facts are added without
 * logging query strings or request bodies. A wired logger that itself fails is the last resort: the
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

    public function __construct(
        private ?LoggerInterface $logger = null,
        private ?MeterInterface $meter = null,
    ) {
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
        if ($status < 400) {
            return;
        }

        $level = $status < self::SERVER_ERROR
            ? 'warning'
            : ($exception instanceof ZefException ? 'error' : 'critical');
        $userId = $serverRequest->getAttribute('user_id');
        $remoteAddress = $serverRequest->getServerParams()['REMOTE_ADDR'] ?? null;
        $userAgent = $serverRequest->getHeaderLine('User-Agent');
        $errorRequestContext = $serverRequest->getAttribute(ErrorRequestContext::ATTRIBUTE);
        $traceId = $exception instanceof ZefException && $exception->getTraceId() !== null
            ? $exception->getTraceId()
            : ($errorRequestContext instanceof ErrorRequestContext ? $errorRequestContext->traceId() : $correlationId);

        try {
            $this->meter?->increment('zef.http.errors.total', 1, [
                'http.request.method' => $serverRequest->getMethod(),
                'http.response.status_code' => $status,
                'exception.type' => self::exceptionType($exception),
            ]);
        } catch (Throwable) {
            // Metrics are best-effort and must never replace the original failure.
        }

        $context = [
            'exception' => $exception,
            'exception.message' => RedactedMessage::of($exception->getMessage()),
            'exception_class' => $exception::class,
            'exception_code' => $exception instanceof ZefException
                ? $exception->getErrorCode()
                : (string) $exception->getCode(),
            'request_id' => $correlationId,
            'trace_id' => $traceId,
            'method' => $serverRequest->getMethod(),
            'path' => $serverRequest->getUri()->getPath(),
            'ip_address' => is_string($remoteAddress) ? $remoteAddress : null,
            'user_agent' => RedactedMessage::of(substr($userAgent, 0, 512)),
        ];

        if (is_string($userId) || is_int($userId)) {
            $context['user_id'] = $userId;
        }

        if ($errorRequestContext instanceof ErrorRequestContext && $errorRequestContext->replayId() !== null) {
            $context['replay_id'] = $errorRequestContext->replayId();
        }

        try {
            if ($this->logger !== null) {
                $this->logger->log($level, 'Request failed', $context);

                return;
            }

            unset($context['exception']);
            error_log((string) json_encode([
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'level' => $level,
                'message' => 'Request failed',
                'context' => $context,
            ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (Throwable $throwable) {
            error_log('ZEF logging failure: ' . $throwable::class);
        }
    }

    private static function exceptionType(Throwable $exception): string
    {
        $class = $exception::class;
        $separator = strrpos($class, '\\');

        return $separator === false ? $class : substr($class, $separator + 1);
    }
}
