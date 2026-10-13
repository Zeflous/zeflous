<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The `Origin` grammar's port component: the port an origin serialises
 * -- its explicit port, unless that port equals the scheme's default
 * (the ports a serialised origin omits from its authority, the PSR-7
 * "SHOULD omit" rule).
 *
 * Attribution: legacy `zef-framework` `Domain/Security/OriginPolicy.php`
 * (`normalizePort()`), move-verbatim behaviour with byte-identical
 * message texts.
 */
final class OriginPort
{
    /**
     * @param null|int $port the explicit port the parse step found, when there was one
     *
     * @return null|int the port to serialise
     *
     * @throws HttpException when the explicit port is zero or negative
     */
    public static function serialised(?int $port, string $scheme): ?int
    {
        if ($port !== null && $port < 1) {
            throw new HttpException('Malformed Origin port.');
        }

        return match (true) {
            $scheme === 'http' && $port === 80 => null,
            $scheme === 'https' && $port === 443 => null,
            default => $port,
        };
    }
}
