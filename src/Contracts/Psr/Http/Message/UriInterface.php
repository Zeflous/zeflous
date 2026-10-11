<?php

declare(strict_types=1);

namespace Psr\Http\Message;

use Stringable;

/**
 * Value object representing a URI, as defined by PSR-7 (RFC 3986).
 *
 * Vendored by the ZEF zero-library shim. Instances are immutable: the with*
 * methods return a new instance with the replaced component, leaving the
 * receiver untouched.
 */
interface UriInterface extends Stringable
{
    /**
     * Returns the string representation of the URI.
     *
     * The scheme is omitted when empty; an explicit port equal to the
     * scheme's default is omitted per RFC 3986 section 6.2.3.
     */
    #[\Override]
    public function __toString(): string;

    /**
     * Retrieves the scheme component.
     *
     * @return string The scheme, lowercased and without the trailing ':'.
     */
    public function getScheme(): string;

    /**
     * Retrieves the authority component: "[userinfo@]host[:port]".
     */
    public function getAuthority(): string;

    /**
     * Retrieves the user information component.
     *
     * @return string The userinfo, e.g. "username:password", or ''.
     */
    public function getUserInfo(): string;

    /**
     * Retrieves the host component.
     *
     * @return string The host, lowercased and without the port.
     */
    public function getHost(): string;

    /**
     * Retrieves the port component.
     *
     * @return int|null The port, or null when absent / the scheme default.
     */
    public function getPort(): ?int;

    /**
     * Retrieves the path component.
     */
    public function getPath(): string;

    /**
     * Retrieves the query string, without the leading '?'.
     */
    public function getQuery(): string;

    /**
     * Retrieves the fragment, without the leading '#'.
     */
    public function getFragment(): string;

    /**
     * Returns an instance with the specified scheme.
     */
    public function withScheme(string $scheme): UriInterface;

    /**
     * Returns an instance with the specified user information.
     */
    public function withUserInfo(string $user, ?string $password = null): UriInterface;

    /**
     * Returns an instance with the specified host.
     */
    public function withHost(string $host): UriInterface;

    /**
     * Returns an instance with the specified port.
     */
    public function withPort(?int $port): UriInterface;

    /**
     * Returns an instance with the specified path.
     */
    public function withPath(string $path): UriInterface;

    /**
     * Returns an instance with the specified query string.
     */
    public function withQuery(string $query): UriInterface;

    /**
     * Returns an instance with the specified URI fragment.
     */
    public function withFragment(string $fragment): UriInterface;
}
