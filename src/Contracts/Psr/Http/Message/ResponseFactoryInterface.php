<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Factory for response objects, as defined by PSR-17.
 *
 * Vendored by the ZEF zero-library shim.
 */
interface ResponseFactoryInterface
{
    /**
     * Creates a new response.
     *
     * @param int $code The HTTP status code (100-599).
     * @param string $reasonPhrase The reason phrase, or '' for the standard
     *        phrase of the code.
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface;
}
