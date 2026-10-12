<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Answers the explicit port a SAPI reports for the request.
 *
 * `SERVER_PORT` is untrusted process state: only a numeric string in the
 * valid range that is not the scheme's default answers a port -- a
 * non-numeric, zero, out-of-range or scheme-default entry is omitted
 * rather than trusted, and a corrupt (non-string) entry is absence like
 * any other ({@see ServerValues}). The reference implementation's
 * validation, move-verbatim; the authority's own port (from the Host
 * header) wins over this fallback in the caller.
 */
final class ServerPort
{
    /**
     * Answers the explicit port for a server array, when a valid
     * non-default one is reported.
     *
     * @param array<string, mixed> $server the SAPI server entries
     * @param 'http'|'https'       $scheme the effective scheme
     *
     * @return null|int the explicit port, or null when omitted
     */
    public static function resolve(array $server, string $scheme): ?int
    {
        $rawPort = ServerValues::text($server, 'SERVER_PORT', '');

        if (!ctype_digit($rawPort)) {
            return null;
        }

        $port = (int) $rawPort;

        if ($port < 1 || $port > 65535 || self::isDefaultSchemePort($scheme, $port)) {
            return null;
        }

        return $port;
    }

    /**
     * Answers whether the port is the scheme's default (80/http, 443/https)
     * -- the ports a URI omits from its authority.
     *
     * @param 'http'|'https' $scheme the effective scheme
     */
    private static function isDefaultSchemePort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
    }
}
