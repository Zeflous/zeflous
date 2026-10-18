<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zef\Framework\Exception\ZefException;

/** Resolves a bounded trace identifier for responses and error logs. */
final class ErrorTraceIdResolver
{
    public static function resolve(
        Throwable $throwable,
        mixed $requestContext,
        string $requestId,
        ServerRequestInterface $request,
    ): string {
        $exceptionTraceId = $throwable instanceof ZefException ? $throwable->getTraceId() : null;
        $candidate = is_string($exceptionTraceId)
            ? $exceptionTraceId
            : ($requestContext instanceof ErrorRequestContext ? $requestContext->traceId() : $requestId);

        return preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $candidate) === 1
            ? $candidate
            : CorrelationId::for($request);
    }
}
