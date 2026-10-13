<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware-to-handler adapter double: runs the wrapped middleware
 * with the terminal handler it wraps -- the shape a test needs to
 * compose a middleware pipeline (an outer middleware delegating into
 * an inner one) without an application kernel.
 */
final class MiddlewareHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly MiddlewareInterface $middleware,
        private readonly RequestHandlerInterface $terminal,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, $this->terminal);
    }
}
