<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Representation of an outgoing, client-side request, as defined by PSR-7.
 *
 * Vendored by the ZEF zero-library shim. Per the HTTP specification, the
 * request message consists of a request line, headers, and a body.
 */
interface RequestInterface extends MessageInterface
{
    /**
     * Retrieves the message's request target as derived from the request,
     * usually the origin-form path plus query, unless an explicit target
     * was provided.
     */
    public function getRequestTarget(): string;

    /**
     * Returns an instance with the explicit request target.
     *
     * Supports origin-form, absolute-form and asterisk-form targets.
     */
    public function withRequestTarget(string $requestTarget): RequestInterface;

    /**
     * Retrieves the HTTP method of the request.
     */
    public function getMethod(): string;

    /**
     * Returns an instance with the provided HTTP method.
     */
    public function withMethod(string $method): RequestInterface;

    /**
     * Retrieves the URI instance.
     */
    public function getUri(): UriInterface;

    /**
     * Returns an instance with the provided URI.
     *
     * When the URI carries a host and preserveHost is false or the message
     * has no Host header yet, the Host header is updated from the URI.
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface;
}
