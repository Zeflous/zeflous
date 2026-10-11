<?php

declare(strict_types=1);

namespace Psr\Http\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Consumes a server request, delegates to the next handler and may act on
 * the response it produced, as defined by PSR-15.
 *
 * Vendored by the ZEF zero-library shim. Middleware composes around the
 * request handler: it may return early with its own response or call the
 * handler to continue the chain.
 */
interface MiddlewareInterface
{
    /**
     * Processes an incoming server request and returns a response.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface;
}
