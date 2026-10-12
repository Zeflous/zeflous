<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\UriInterface;
use Zef\Framework\Http\Uri;

/**
 * Deterministic UriInterface double for the factory tests: a distinct
 * object identity over the real value object's components, so a factory
 * test can prove the passed instance itself travelled through (the
 * identity the PSR-17 contract preserves) without depending on the
 * value object's own test suite. Every answer delegates to a real
 * `Uri`, keeping the double's components honest.
 */
final class UriObjectDouble implements UriInterface
{
    private UriInterface $uri;

    public function __construct(string $uri)
    {
        $this->uri = new Uri($uri);
    }

    #[Override]
    public function __toString(): string
    {
        return (string) $this->uri;
    }

    #[Override]
    public function getScheme(): string
    {
        return $this->uri->getScheme();
    }

    #[Override]
    public function getAuthority(): string
    {
        return $this->uri->getAuthority();
    }

    #[Override]
    public function getUserInfo(): string
    {
        return $this->uri->getUserInfo();
    }

    #[Override]
    public function getHost(): string
    {
        return $this->uri->getHost();
    }

    #[Override]
    public function getPort(): ?int
    {
        return $this->uri->getPort();
    }

    #[Override]
    public function getPath(): string
    {
        return $this->uri->getPath();
    }

    #[Override]
    public function getQuery(): string
    {
        return $this->uri->getQuery();
    }

    #[Override]
    public function getFragment(): string
    {
        return $this->uri->getFragment();
    }

    #[Override]
    public function withScheme(string $scheme): UriInterface
    {
        $this->uri = $this->uri->withScheme($scheme);

        return $this;
    }

    #[Override]
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $this->uri = $this->uri->withUserInfo($user, $password);

        return $this;
    }

    #[Override]
    public function withHost(string $host): UriInterface
    {
        $this->uri = $this->uri->withHost($host);

        return $this;
    }

    #[Override]
    public function withPort(?int $port): UriInterface
    {
        $this->uri = $this->uri->withPort($port);

        return $this;
    }

    #[Override]
    public function withPath(string $path): UriInterface
    {
        $this->uri = $this->uri->withPath($path);

        return $this;
    }

    #[Override]
    public function withQuery(string $query): UriInterface
    {
        $this->uri = $this->uri->withQuery($query);

        return $this;
    }

    #[Override]
    public function withFragment(string $fragment): UriInterface
    {
        $this->uri = $this->uri->withFragment($fragment);

        return $this;
    }
}
