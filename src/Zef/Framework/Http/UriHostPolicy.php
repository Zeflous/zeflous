<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\UriInterface;

/**
 * The policy binding the Host header to the URI's host: which Host header
 * a newly constructed request carries (set from the URI only when the
 * message has none -- the seam the reference implementation's
 * `addInitialHeader()` exposed) and when `withUri()` refreshes it (the
 * PSR-7 preserve-host rule: an existing Host header wins while the caller
 * preserves it, and a hostless URI never carries one over). Pure static
 * helper -- the same shape as the other HTTP grammars.
 */
final class UriHostPolicy
{
    /**
     * Answers the header store a newly constructed request carries: the
     * URI's host line is added only when the message carries no Host
     * header, so an explicit header always wins over the URI.
     */
    public static function bootstrapped(HeaderStore $headerStore, UriInterface $uri): HeaderStore
    {
        if ($headerStore->has('Host') || $uri->getHost() === '') {
            return $headerStore;
        }

        return $headerStore->with('Host', HostLine::fromUri($uri));
    }

    /**
     * Answers whether `withUri()` refreshes the Host header from the new
     * URI: it does unless the caller preserves an existing Host header
     * (an empty Host line counts as absent, mirroring the header-line
     * reading the reference implementation consulted), and a hostless URI
     * never triggers a refresh.
     */
    public static function shouldRefresh(UriInterface $uri, bool $preserveHost, string $existingHostLine): bool
    {
        return (!$preserveHost || $existingHostLine === '') && $uri->getHost() !== '';
    }
}
