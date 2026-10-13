<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The `Origin` grammar's normal form: `scheme://host[:port]` and nothing
 * else -- the serialisation two origins are compared through.
 *
 * The privacy origin `null` is its own comparison form (the string the
 * fetch spec sends for an opaque origin, sandboxed frames among them); a
 * carriage return or line feed anywhere inside the value is malformed
 * before anything else is even attempted (the header-smuggling guard --
 * a CR or LF at the very edges is trimmed away first, exactly as the
 * reference's `trim()` behaved); and the parse step rejects credentials,
 * a path, a query or a fragment -- an origin is authority only. The
 * scheme is answered in its canonical spelling ({@see canonicalScheme()}),
 * the host in its comparison form ({@see OriginHost}), and the port
 * through {@see OriginPort}.
 *
 * Attribution: legacy `zef-framework` `Domain/Security/OriginPolicy.php`
 * (`normalizeOrigin()`), move-verbatim behaviour with byte-identical
 * message texts, folded onto the layer's single exception root. The
 * scheme's case fold is a canonical-spelling match instead of a
 * lower-case pass -- `parse_url()`'s scheme component charset is ASCII
 * only, so every scheme reaching a fold is pure ASCII and the fold's
 * character map can never differ from its byte map; the match returns
 * the canonical literal itself, which keeps every branch observable
 * without an analysis ignore.
 */
final class CorsOrigin
{
    /**
     * @throws HttpException when the origin is malformed, carries a
     *                       non-HTTP scheme, or carries an invalid port
     */
    public static function normalize(string $origin): string
    {
        $origin = mb_trim($origin);

        if ($origin === 'null') {
            return 'null';
        }

        if (preg_match('/[\r\n]/', $origin) === 1) {
            throw new HttpException('Malformed Origin header.');
        }

        [$scheme, $host, $port] = OriginParser::parse($origin);
        $scheme = self::canonicalScheme($scheme);
        $host = OriginHost::normalize($host);
        $port = OriginPort::serialised($port, $scheme);

        return $scheme . '://' . (str_contains($host, ':') ? '[' . $host . ']' : $host)
            . ($port === null ? '' : ':' . $port);
    }

    /**
     * Answers the scheme's canonical spelling: `http` or `https`, in any
     * of the cases the request spelled them.
     *
     * @throws HttpException when the scheme is anything else
     */
    private static function canonicalScheme(string $scheme): string
    {
        return match (true) {
            strcasecmp($scheme, 'http') === 0 => 'http',
            strcasecmp($scheme, 'https') === 0 => 'https',
            default => throw new HttpException('Unsupported Origin scheme.'),
        };
    }
}
