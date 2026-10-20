<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * A delegating request double that answers raw, unvalidated request
 * facts: an arbitrary method token, an arbitrary URI, and an arbitrary
 * header map. The real {@see \Zef\Framework\Http\ServerRequest} refuses
 * non-token methods and non-ASCII header names at its boundary, so the
 * replay snapshot's own normalisation (upper-casing the method, folding
 * header names) can only be proven against multibyte facts through a
 * double. Everything the snapshot does not re-derive (the body, the
 * attribute bag) keeps the inner request's honest answers.
 */
final class RawFactsServerRequest implements ServerRequestInterface
{
    /**
     * @param array<string, list<string>> $headers the raw header map to answer
     */
    public function __construct(
        private readonly ServerRequestInterface $inner,
        private readonly string $method,
        private readonly UriInterface $uri,
        private readonly array $headers,
    ) {
    }

    #[Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    #[Override]
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /** @return array<string, list<string>> */
    #[Override]
    public function getHeaders(): array
    {
        return $this->headers;
    }

    #[Override]
    public function withMethod(string $method): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getServerParams(): array
    {
        return $this->inner->getServerParams();
    }

    #[Override]
    public function getCookieParams(): array
    {
        return $this->inner->getCookieParams();
    }

    #[Override]
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getQueryParams(): array
    {
        return $this->inner->getQueryParams();
    }

    #[Override]
    public function withQueryParams(array $query): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getUploadedFiles(): array
    {
        return $this->inner->getUploadedFiles();
    }

    #[Override]
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getParsedBody(): mixed
    {
        return $this->inner->getParsedBody();
    }

    #[Override]
    public function withParsedBody(mixed $data): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getAttributes(): array
    {
        return $this->inner->getAttributes();
    }

    #[Override]
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->inner->getAttribute($name, $default);
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
        return $this->inner->getRequestTarget();
    }

    #[Override]
    public function withRequestTarget(string $requestTarget): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function withUri(UriInterface $uri, bool $preserveHost = false): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function getProtocolVersion(): string
    {
        return $this->inner->getProtocolVersion();
    }

    #[Override]
    public function withProtocolVersion(string $version): ServerRequestInterface
    {
        return $this;
    }

    #[Override]
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    #[Override]
    public function getHeader(string $name): array
    {
        return $this->headers[$name] ?? [];
    }

    #[Override]
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->headers[$name] ?? []);
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
        return $this->inner->getBody();
    }

    #[Override]
    public function withBody(StreamInterface $body): ServerRequestInterface
    {
        return $this;
    }
}
