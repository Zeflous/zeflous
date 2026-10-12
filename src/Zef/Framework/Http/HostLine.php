<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\UriInterface;

/**
 * The Host header line rendered from a URI: an IPv6 literal host is
 * bracketed the way the URI authority spells it ({@see Uri} stores the
 * address bare), and a non-default port rides along -- the URI already
 * nulls a port equal to the scheme's default, so the rendered line never
 * carries one. Pure static helper; both call sites ({@see UriHostPolicy}
 * bootstrap and refresh) consult it only for a URI that carries a host,
 * which the empty-host case answers an empty line for anyway. Mirrors
 * the reference implementation (legacy `zef-framework`
 * `RequestTrait::hostHeaderFromUri`).
 */
final class HostLine
{
    /**
     * Renders the Host header value for a URI that carries a host.
     */
    public static function fromUri(UriInterface $uri): string
    {
        $host = $uri->getHost();
        $authority = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $port = $uri->getPort();
        if ($port !== null) {
            $authority .= ':' . $port;
        }

        return $authority;
    }
}
