<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Deterministic method-casing double: delegates every answer to a real
 * server request while answering a lowercase method token. PSR-7 carries
 * no case canonicalisation guarantee (the method is a case-sensitive
 * token whose registered forms happen to be spelled uppercase), so a
 * middleware must not assume canonical casing -- this double is the
 * shape that makes the assumption observable.
 */
final class LowercaseMethodServerRequest implements ServerRequestInterface
{
    public function __construct(private readonly ServerRequestInterface $inner)
    {
    }

    #[Override]
    public function getMethod(): string
    {
        return 'get';
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
    public function getUri(): UriInterface
    {
        return $this->inner->getUri();
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
    public function getHeaders(): array
    {
        return $this->inner->getHeaders();
    }

    #[Override]
    public function hasHeader(string $name): bool
    {
        return $this->inner->hasHeader($name);
    }

    #[Override]
    public function getHeader(string $name): array
    {
        return $this->inner->getHeader($name);
    }

    #[Override]
    public function getHeaderLine(string $name): string
    {
        return $this->inner->getHeaderLine($name);
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
