<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Http\CorrelationId;
use Zef\Framework\Http\ErrorRequestContext;

/** Appends a bounded, development-only toolbar to eligible HTML responses. */
final readonly class DebugToolbarMiddleware implements MiddlewareInterface
{
    public function __construct(private bool $enabled = false)
    {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startedAt = hrtime(true);
        $response = $handler->handle($request);
        if (
            !$this->enabled
            || $response->hasHeader('Content-Security-Policy')
            || !DebugToolbarEligibility::allows($request, $response)
        ) {
            return $response;
        }

        $context = ErrorRequestContext::fromAttribute($request->getAttribute(ErrorRequestContext::ATTRIBUTE));
        $duration = $context !== null
            ? $context->durationMilliseconds()
            : (hrtime(true) - $startedAt) / 1_000_000;
        $traceId = $context !== null
            ? $context->traceId()
            : CorrelationId::for($request);

        return DebugToolbarInjector::inject(
            $response,
            $duration,
            $context instanceof ErrorRequestContext ? $context->queryCount() : 0,
            $context instanceof ErrorRequestContext ? $context->exceptionCount() : 0,
            $traceId,
        );
    }
}
