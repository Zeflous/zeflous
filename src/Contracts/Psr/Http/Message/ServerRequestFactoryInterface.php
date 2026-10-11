<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Factory for server-side request objects, as defined by PSR-17.
 *
 * Vendored by the ZEF zero-library shim.
 */
interface ServerRequestFactoryInterface
{
    /**
     * Creates a new server-side request.
     *
     * @param string $method The HTTP method.
     * @param string|UriInterface $uri The URI, either a string or an object.
     * @param array<string, mixed> $serverParams The server parameters
     *        (typically $_SERVER).
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface;
}
