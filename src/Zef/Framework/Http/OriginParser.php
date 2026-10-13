<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The `Origin` grammar's parse step: `scheme://host[:port]` through
 * `parse_url()`, with everything an origin must NOT carry rejected --
 * credentials of any shape (a `user` component alone counts, audit #330 in
 * the reference lineage: the old conjunction let `https://user@host`
 * through and silently dropped the userinfo instead of rejecting it), a
 * path, a query, or a fragment.
 *
 * The port is answered without a cast: `parse_url()`'s `port` component
 * is an engine-typed int (a non-numeric port fails the parse outright),
 * so the reference's defensive `(int)` cast was a no-op coercion and is
 * dropped rather than kept as dead surface. The host is answered in its
 * raw spelling -- its comparison form belongs to {@see OriginHost}, the
 * component's own grammar owner.
 *
 * Attribution: legacy `zef-framework` `Domain/Security/OriginPolicy.php`
 * (`parseOrigin()`), move-verbatim behaviour with byte-identical message
 * texts, folded onto the layer's single exception root.
 */
final class OriginParser
{
    /**
     * @return array{string, string, null|int} the scheme and host in
     *                                         their raw spellings, and the explicit port when
     *                                         the origin carried one
     *
     * @throws HttpException when the value is not exactly scheme, host
     *                       and an optional port
     */
    public static function parse(string $origin): array
    {
        $parts = parse_url($origin);

        $carriesCredentials = isset($parts['user']) || isset($parts['pass']);
        $carriesExtra = isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '');

        if (
            $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || $carriesCredentials
            || $carriesExtra
        ) {
            throw new HttpException('Malformed Origin header.');
        }

        return [
            $parts['scheme'],
            $parts['host'],
            $parts['port'] ?? null,
        ];
    }
}
