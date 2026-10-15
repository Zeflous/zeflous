<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the effective client IP address the rate-limit family throttles
 * by, honouring the trusted-proxy boundary.
 *
 * `REMOTE_ADDR` (the direct peer) is the client unless it is itself a
 * trusted proxy; only then is `X-Forwarded-For` consulted, and only its
 * first syntactically valid candidate is honoured -- every other client
 * of an untrusted proxy keeps its peer address, so a spoofed forwarded
 * header never crosses the ingress ({@see TrustedProxyMatcher}, the
 * layer's existing CIDR-capable matcher; the reference carried a private
 * duplicate of that arithmetic, which this port composes instead).
 *
 * The answer is always a validated address: an absent, corrupt or
 * unparseable peer resolves to the `0.0.0.0` sentinel -- one well-known
 * bucket rather than an attacker-chosen string -- and a trusted proxy
 * whose forwarded candidates are all invalid falls back to its own
 * address. A non-string `REMOTE_ADDR` entry is process corruption and
 * reads exactly like absence ({@see ServerValues}, the layer's single
 * reading policy for SAPI entries).
 *
 * Attribution: legacy `zef-main` `Framework/Security/
 * ClientAddressResolver.php` (move-verbatim resolution order and the
 * `0.0.0.0` sentinel; the trusted-proxy check composes this layer's
 * own matcher).
 */
final class ClientAddress
{
    /**
     * Answers the request's effective client address.
     *
     * @param ServerRequestInterface $serverRequest  the request whose peer
     *                                               and forwarded values are
     *                                               read
     * @param list<string>           $trustedProxies the exact addresses or
     *                                               CIDR networks allowed to
     *                                               supply `X-Forwarded-For`
     */
    public static function resolve(ServerRequestInterface $serverRequest, array $trustedProxies = []): string
    {
        $remote = ServerValues::text($serverRequest->getServerParams(), 'REMOTE_ADDR', '');

        if ($remote === '' || !TrustedProxyMatcher::matches($remote, $trustedProxies)) {
            return self::sanitized($remote);
        }

        $forwarded = $serverRequest->getHeaderLine('X-Forwarded-For');

        if ($forwarded !== '') {
            foreach (self::candidates($forwarded) as $candidate) {
                if (filter_var($candidate, \FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }
        }

        return self::sanitized($remote);
    }

    /**
     * The validated address, or the `0.0.0.0` sentinel when the input is
     * empty or not a syntactically valid address.
     */
    private static function sanitized(string $ip): string
    {
        if ($ip !== '' && filter_var($ip, \FILTER_VALIDATE_IP) !== false) {
            return $ip;
        }

        return '0.0.0.0';
    }

    /**
     * The forwarded header's comma-separated candidates, each trimmed.
     *
     * @return list<string>
     */
    private static function candidates(string $forwarded): array
    {
        return array_map(mb_trim(...), explode(',', $forwarded));
    }
}
