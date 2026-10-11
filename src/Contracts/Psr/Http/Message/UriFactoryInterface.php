<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Factory for URI objects, as defined by PSR-17.
 *
 * Vendored by the ZEF zero-library shim.
 */
interface UriFactoryInterface
{
    /**
     * Creates a new URI.
     *
     * @param string $uri The URI string to parse.
     */
    public function createUri(string $uri = ''): UriInterface;
}
