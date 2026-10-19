<?php

declare(strict_types=1);

namespace Zef\Test\Support\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Middleware\CorrelationMiddleware;

/**
 * A request handler that delegates straight into one middleware's
 * process(): the composition tests stack the correlation boundary inside
 * an outer error boundary, and this handler is the innermost leg that
 * hands control to the middleware under test with a configurable next
 * handler (typically one that throws the failure the outer boundary is
 * expected to render).
 */
final class DelegatingHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly CorrelationMiddleware $middleware,
        private readonly RequestHandlerInterface $next,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, $this->next);
    }
}
