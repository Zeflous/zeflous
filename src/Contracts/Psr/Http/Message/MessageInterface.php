<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Describes the base interface of HTTP messages.
 *
 * Vendored by the ZEF zero-library shim from PSR-7; both request and response
 * message interfaces descend from it. Method contracts follow the upstream
 * wording; signatures carry the type declarations this repository analyses by.
 */
interface MessageInterface
{
    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * @return non-empty-string HTTP protocol version, e.g. "1.1" or "2.0".
     */
    public function getProtocolVersion(): string;

    /**
     * Returns an instance with the specified HTTP protocol version.
     *
     * @param non-empty-string $version HTTP protocol version.
     */
    public function withProtocolVersion(string $version): MessageInterface;

    /**
     * Retrieves all message headers, keyed by their original case.
     *
     * @return array<string, list<string>> Header names => list of values.
     */
    public function getHeaders(): array;

    /**
     * Checks if a header exists by the given (case-insensitive) name.
     */
    public function hasHeader(string $name): bool;

    /**
     * Retrieves a message header value by the given (case-insensitive) name.
     *
     * @return list<string> The header values, or an empty list if absent.
     */
    public function getHeader(string $name): array;

    /**
     * Retrieves a comma-separated string of the values for a single header.
     */
    public function getHeaderLine(string $name): string;

    /**
     * Returns an instance with the provided header, replacing any existing
     * values of that header.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|list<string> $value Header value(s).
     */
    public function withHeader(string $name, $value): MessageInterface;

    /**
     * Returns an instance with the appended header value(s).
     *
     * Existing and new values are combined; the header is created when absent.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|list<string> $value Header value(s) to append.
     */
    public function withAddedHeader(string $name, $value): MessageInterface;

    /**
     * Returns an instance without the specified header.
     *
     * @param string $name Case-insensitive header field name.
     */
    public function withoutHeader(string $name): MessageInterface;

    /**
     * Gets the body of the message.
     */
    public function getBody(): StreamInterface;

    /**
     * Returns an instance with the specified message body.
     */
    public function withBody(StreamInterface $body): MessageInterface;
}
