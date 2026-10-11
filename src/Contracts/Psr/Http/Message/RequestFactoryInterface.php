<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Factory for request objects, as defined by PSR-17.
 *
 * Vendored by the ZEF zero-library shim.
 */
interface RequestFactoryInterface
{
    /**
     * Creates a new request.
     *
     * @param string $method The HTTP method.
     * @param string|UriInterface $uri The URI, either a string or an object.
     */
    public function createRequest(string $method, $uri): RequestInterface;
}
