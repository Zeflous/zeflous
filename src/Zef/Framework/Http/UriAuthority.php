<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The URI authority renderer: "[userinfo@]host[:port]" over one component
 * set.
 *
 * Pure static helper for the PSR-7 {@see Uri} value object. An explicit
 * port equal to the scheme's default is omitted per RFC 3986 section
 * 6.2.3; IPv6 hosts are bracketed per RFC 9110 section 7.2.
 */
final class UriAuthority
{
    /**
     * @param array{
     *     scheme: string,
     *     userInfo: string,
     *     host: string,
     *     port: null|int,
     *     path: string,
     *     query: string,
     *     fragment: string,
     * } $components
     */
    public static function render(array $components): string
    {
        if ($components['host'] === '') {
            return '';
        }

        $authority = ($components['userInfo'] !== '' ? $components['userInfo'] . '@' : '')
            . self::bracketedHost($components['host']);
        $port = $components['port'];
        if ($port !== null && !UriGrammar::isDefaultPortForScheme($components['scheme'], $port)) {
            $authority .= ':' . $port;
        }

        return $authority;
    }

    private static function bracketedHost(string $host): string
    {
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            return '[' . $host . ']';
        }

        return $host;
    }
}
