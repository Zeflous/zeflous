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
        $errorRequestContext = ErrorRequestContext::fromAttribute(
            $request->getAttribute(ErrorRequestContext::ATTRIBUTE),
        );
        $traceId = ErrorTraceIdResolver::resolve($exception, $errorRequestContext, $correlationId, $request);
        $context = [
            'exception.message' => RedactedMessage::of(mb_substr($exception->getMessage(), 0, 2048)),
            'exception_class' => $exception::class,
            'exception_code' => $exception instanceof ZefException
                ? $exception->getErrorCode()
                : (string) $exception->getCode(),
            'request_id' => $correlationId,
            'trace_id' => $traceId,
            'method' => $request->getMethod(),
            'path' => mb_substr($request->getUri()->getPath(), 0, 2048),
        ];

        if ($errorRequestContext instanceof ErrorRequestContext && $errorRequestContext->replayId() !== null) {
            $context['replay_id'] = $errorRequestContext->replayId();
        }

        return $context;
    }
}
