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
final readonly class DelegatingHandler implements RequestHandlerInterface
{
    public function __construct(
        private CorrelationMiddleware $correlationMiddleware,
        private RequestHandlerInterface $requestHandler,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $serverRequest): ResponseInterface
    {
        return $this->correlationMiddleware->process($serverRequest, $this->requestHandler);
    }
}
