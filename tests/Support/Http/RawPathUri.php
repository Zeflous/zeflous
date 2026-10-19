<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\UriInterface;

/**
 * A UriInterface double that answers an arbitrary, unvalidated path and
 * query. The real {@see \Zef\Framework\Http\Uri} normalises and rejects
 * control characters, so the replay store's defensive path guard can only
 * be exercised through a double that answers the raw bytes a hostile
 * client could deliver below the URI layer's own validation.
 */
final class RawPathUri implements UriInterface
{
    public function __construct(
        private readonly string $path,
        private readonly string $query = '',
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->path . ($this->query === '' ? '' : '?' . $this->query);
    }

    #[Override]
    public function getScheme(): string
    {
        return '';
    }

    #[Override]
    public function getAuthority(): string
    {
        return '';
    }

    #[Override]
    public function getUserInfo(): string
    {
        return '';
    }

    #[Override]
    public function getHost(): string
    {
        return '';
    }

    #[Override]
    public function getPort(): ?int
    {
        return null;
    }

    #[Override]
    public function getPath(): string
    {
        return $this->path;
    }

    #[Override]
    public function getQuery(): string
    {
        return $this->query;
    }

    #[Override]
    public function getFragment(): string
    {
        return '';
    }

    #[Override]
    public function withScheme(string $scheme): UriInterface
    {
        return $this;
    }

    #[Override]
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        return $this;
    }

    #[Override]
    public function withHost(string $host): UriInterface
    {
        return $this;
    }

    #[Override]
    public function withPort(?int $port): UriInterface
    {
        return $this;
    }

    #[Override]
    public function withPath(string $path): UriInterface
    {
        return $this;
    }

    #[Override]
    public function withQuery(string $query): UriInterface
    {
        return $this;
    }

    #[Override]
    public function withFragment(string $fragment): UriInterface
    {
        return $this;
    }
}
