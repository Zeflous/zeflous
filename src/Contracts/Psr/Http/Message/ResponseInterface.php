<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Representation of an outgoing, server-side response, as defined by PSR-7.
 *
 * Vendored by the ZEF zero-library shim. Per the HTTP specification, the
 * response message consists of a status code, reason phrase, headers and
 * a body.
 */
interface ResponseInterface extends MessageInterface
{
    /**
     * Gets the response status code.
     */
    public function getStatusCode(): int;

    /**
     * Returns an instance with the specified status code and, optionally,
     * reason phrase.
     */
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface;

    /**
     * Gets the response reason phrase associated with the status code.
     *
     * The reason phrase is empty when none was supplied and the code has no
     * standard phrase.
     */
    public function getReasonPhrase(): string;
}
