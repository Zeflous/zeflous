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
        Throwable $throwable,
        string $correlationId,
        ServerRequestInterface $serverRequest,
    ): array {
        $errorRequestContext = ErrorRequestContext::fromAttribute(
            $serverRequest->getAttribute(ErrorRequestContext::ATTRIBUTE),
        );
        $traceId = ErrorTraceIdResolver::resolve($throwable, $errorRequestContext, $correlationId, $serverRequest);
        $context = [
            'exception.message' => RedactedMessage::of(mb_substr($throwable->getMessage(), 0, 2048)),
            'exception_class' => $throwable::class,
            'exception_code' => $throwable instanceof ZefException
                ? $throwable->getErrorCode()
                : (string) $throwable->getCode(),
            'request_id' => $correlationId,
            'trace_id' => $traceId,
            'method' => $serverRequest->getMethod(),
            'path' => mb_substr($serverRequest->getUri()->getPath(), 0, 2048),
        ];

        if ($errorRequestContext instanceof ErrorRequestContext && $errorRequestContext->replayId() !== null) {
            $context['replay_id'] = $errorRequestContext->replayId();
        }

        return $context;
    }
}
