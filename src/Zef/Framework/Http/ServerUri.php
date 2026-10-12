<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Builds the request URI from the SAPI entries that describe the origin.
 *
 * The authority comes from `HTTP_HOST` (the client-sent Host header) with
 * `SERVER_NAME` as the SAPI-configured fallback; a hostless server array
 * answers the `localhost` authority so a CLI-simulated request still
 * carries an absolute URI. The scheme is the request's own HTTPS state
 * and the port a validated non-default `SERVER_PORT` -- a non-numeric,
 * zero, out-of-range or scheme-default port is omitted rather than
 * trusted ({@see ServerPort}), and an IPv6 literal is bracketed per RFC
 * 3986 §3.2.2. Forwarded headers are deliberately NOT consulted:
 * honouring `X-Forwarded-*` is a trusted-proxy policy decision the
 * security slices own, never a default the extraction silently makes.
 */
final class ServerUri
{
    /**
     * Builds the request URI from a server array.
     *
     * @param array<string, mixed> $server the SAPI server entries, typically $_SERVER
     *
     * @throws HttpException when the host authority is malformed
     */
    public static function build(array $server): Uri
    {
        [$path, $query] = ServerPath::split(ServerValues::text($server, 'REQUEST_URI', ''));
        [$host, $authorityPort] = HostAuthority::parse(self::authority($server));
        $scheme = self::scheme($server);
        $port = $authorityPort ?? ServerPort::resolve($server, $scheme);
        $path = FrontController::stripScriptPrefix($server, $path);
        $hostLiteral = str_contains($host, ':') ? '[' . $host . ']' : $host;

        $uri = $scheme . '://'
            . ($hostLiteral !== '' ? $hostLiteral : 'localhost')
            . ($port !== null ? ':' . $port : '')
            . $path
            . ($query !== '' ? '?' . $query : '');

        return new Uri($uri);
    }

    /**
     * Answers the authority literal the request carries: the client-sent
     * Host header, falling back to the SAPI-configured server name when
     * the header is absent, empty or corrupt.
     *
     * @param array<string, mixed> $server the SAPI server entries
     *
     * @return string the authority literal, empty when neither source
     *                describes one
     */
    private static function authority(array $server): string
    {
        $hostHeader = ServerValues::text($server, 'HTTP_HOST', '');

        return $hostHeader !== '' ? $hostHeader : ServerValues::text($server, 'SERVER_NAME', '');
    }

    /**
     * Answers the request scheme from its HTTPS state: TLS when the entry
     * carries a non-empty string other than the engine's falsy string
     * ('0') and the FPM's literal `off` marker; plain http otherwise.
     *
     * @param array<string, mixed> $server the SAPI server entries
     *
     * @return 'http'|'https' the effective scheme
     */
    private static function scheme(array $server): string
    {
        return self::isHttps($server['HTTPS'] ?? null) ? 'https' : 'http';
    }

    /**
     * Answers whether a raw HTTPS state entry describes TLS: a non-empty
     * string other than the engine's falsy string ('0') and the FPM's
     * literal `off` marker. Non-string corruption is absence, not TLS.
     *
     * @param mixed $https the raw HTTPS entry, null when the key is absent
     */
    private static function isHttps(mixed $https): bool
    {
        return \is_string($https) && $https !== '' && $https !== '0' && $https !== 'off';
    }
}
