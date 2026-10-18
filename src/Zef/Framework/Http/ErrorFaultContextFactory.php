<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zef\Framework\Exception\ZefException;

/** Builds bounded, redacted request facts for a single error record. */
final class ErrorFaultContextFactory
{
    /** @return array<string, mixed> */
    public static function create(
        Throwable $exception,
        string $correlationId,
        ServerRequestInterface $request,
    ): array {
        $errorRequestContext = $request->getAttribute(ErrorRequestContext::ATTRIBUTE);
        $traceId = ErrorTraceIdResolver::resolve($exception, $errorRequestContext, $correlationId, $request);
        $context = [
            'exception' => $exception,
            'exception.message' => RedactedMessage::of(substr($exception->getMessage(), 0, 2048)),
            'exception_class' => $exception::class,
            'exception_code' => $exception instanceof ZefException
                ? $exception->getErrorCode()
                : (string) $exception->getCode(),
            'request_id' => $correlationId,
            'trace_id' => $traceId,
            'method' => $request->getMethod(),
            'path' => substr($request->getUri()->getPath(), 0, 2048),
            'ip_address' => self::remoteAddress($request),
            'user_agent' => RedactedMessage::of(substr($request->getHeaderLine('User-Agent'), 0, 512)),
        ];

        $userId = $request->getAttribute('user_id');
        if (is_string($userId) || is_int($userId)) {
            $context['user_id'] = $userId;
        }

        if ($errorRequestContext instanceof ErrorRequestContext && $errorRequestContext->replayId() !== null) {
            $context['replay_id'] = $errorRequestContext->replayId();
        }

        return $context;
    }

    private static function remoteAddress(ServerRequestInterface $request): ?string
    {
        $remoteAddress = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($remoteAddress) ? $remoteAddress : null;
    }
}
