<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Deterministic foreign server-request double: carries whatever URI,
 * server bag, header map and body stream it is constructed with, with
 * none of the layer's construction-time validation -- in particular, no
 * Host bootstrap from the URI. The ingress gate re-judges a request that
 * was built elsewhere precisely because such an implementation is
 * allowed to exist: a request whose header map genuinely lacks a Host
 * field (the HTTP/1.0 client shape) answers the URI host as its only
 * host carrier, and the gate's fallback branch is observable only
 * through it.
 *
 * The header map is honest about its shape (lowercased names to
 * single-value lists, the CGI extraction's deterministic spelling); the
 * case-insensitive lookup the vendored contract promises is answered
 * through a lowercase fold, the same resolution the layer's own store
 * performs.
 */
final class ForeignServerRequest implements ServerRequestInterface
{
    /**
     * @param array<string, mixed>        $serverParams the server bag the double answers verbatim
     * @param array<string, list<string>> $headers      the header map the double answers
     *                                                  verbatim (an absent name is an absent header)
     * @param StreamInterface             $body         the body stream the double answers
     */
    public function __construct(
        private readonly UriInterface $uri,
        private readonly array $serverParams,
        private readonly array $headers,
        private readonly StreamInterface $body,
    ) {
    }

    #[Override]
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    #[Override]
    public function getCookieParams(): array
    {
        return [];
    }

    #[Override]
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getQueryParams(): array
    {
        return [];
    }

    #[Override]
    public function withQueryParams(array $query): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getUploadedFiles(): array
    {
        return [];
    }

    #[Override]
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getParsedBody(): mixed
    {
        return null;
    }

    #[Override]
    public function withParsedBody(mixed $data): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getAttributes(): array
    {
        return [];
    }

    #[Override]
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $default;
    }

    #[Override]
    public function withAttribute(string $name, mixed $value): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function withoutAttribute(string $name): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getRequestTarget(): string
    {
        return '/';
    }

    #[Override]
    public function withRequestTarget(string $requestTarget): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getMethod(): string
    {
        return 'GET';
    }

    #[Override]
    public function withMethod(string $method): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    #[Override]
    public function withUri(UriInterface $uri, bool $preserveHost = false): ServerRequestInterface
    {
        return new self($uri, $this->serverParams, $this->headers, $this->body);
    }

    #[Override]
    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    #[Override]
    public function withProtocolVersion(string $version): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getHeaders(): array
    {
        return $this->headers;
    }

    #[Override]
    public function hasHeader(string $name): bool
    {
        return \array_key_exists(mb_strtolower($name), $this->headers);
    }

    #[Override]
    public function getHeader(string $name): array
    {
        return $this->headers[mb_strtolower($name)] ?? [];
    }

    #[Override]
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    #[Override]
    public function withHeader(string $name, mixed $value): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function withAddedHeader(string $name, mixed $value): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function withoutHeader(string $name): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    #[Override]
    public function withBody(StreamInterface $body): ServerRequestInterface
    {
        return new self($this->uri, $this->serverParams, $this->headers, $body);
    }
}
