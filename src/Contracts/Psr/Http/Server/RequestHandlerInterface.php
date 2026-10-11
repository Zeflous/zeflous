<?php

declare(strict_types=1);

namespace Psr\Http\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles a server request and produces a response, as defined by PSR-15.
 *
 * Vendored by the ZEF zero-library shim. A request handler is the terminal
 * consumer of a middleware pipeline: it performs the application's work for
 * the request and returns the response.
 */
interface RequestHandlerInterface
{
    /**
     * Handles a request and produces a response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
