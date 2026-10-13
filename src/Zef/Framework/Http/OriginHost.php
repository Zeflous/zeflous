<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The `Origin` grammar's host component: an IPv6 literal stripped of the
 * brackets `parse_url()` keeps it wrapped in, or an IPv4 or registered
 * hostname -- one DNS label per RFC 1123 (alphanumeric edges, inner
 * hyphens, at most 63 characters each, 253 overall).
 *
 * The comparison form is the host lower-cased through the engine's full
 * Unicode case map BEFORE the grammar is judged, so a host spelled with
 * the Kelvin sign (`X` + U+212A) compares as the ASCII `k` it lower-cases
 * to and passes the ASCII-only hostname grammar -- the same fold the
 * trusted-host comparison performs ({@see TrustedHosts}). The bracket
 * strip is one anchored pattern over the whole literal instead of indexed
 * byte probes, the layer's grammar-collaborator idiom.
 *
 * Attribution: legacy `zef-framework` `Domain/Security/OriginPolicy.php`
 * (`normalizeIpv6Host()` and `assertHostGrammar()`), move-verbatim
 * behaviour with byte-identical message texts. The fold moved here from
 * the reference's parse step: it is the host component's own comparison
 * form, and owning it keeps the fold's character-map behaviour
 * observable in one place.
 */
final class OriginHost
{
    /** Single DNS label per RFC 1123: alphanumeric edges, inner hyphens. */
    private const string HOST_LABEL = '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';

    private const string HOST_NAME_PATTERN = '/^(?=.{1,253}$)' . self::HOST_LABEL
        . '(?:\.' . self::HOST_LABEL . ')*$/';

    /**
     * @throws HttpException when the host is neither an IP literal nor a
     *                       registered hostname
     */
    public static function normalize(string $host): string
    {
        $comparisonForm = mb_strtolower($host);
        $bare = self::unbracketed($comparisonForm);
        self::assertGrammar($bare);

        return $bare;
    }

    /**
     * Strips the brackets an IPv6 literal travels in: `parse_url()` keeps
     * `[::1]` bracketed, and the grammar and the serialisation need the
     * bare address. The pattern is anchored at both ends -- a bracket
     * pair anywhere else in the host (`a[b]`) is not an IPv6 literal and
     * must fail the grammar below instead of being silently stripped.
     *
     * No `s` flag: the only characters it would widen `.` to cover -- a
     * carriage return or line feed inside the brackets -- are rejected by
     * the origin's own smuggling guard before this pattern ever runs, so
     * the flag was grammar no reachable input could observe.
     */
    private static function unbracketed(string $host): string
    {
        if (preg_match('~^\[(.*)\]\z~', $host, $brackets) === 1) {
            return $brackets[1];
        }

        return $host;
    }

    /**
     * Asserts the bare host is an IP literal (either family) or a
     * registered hostname.
     *
     * @throws HttpException when it is neither
     */
    private static function assertGrammar(string $host): void
    {
        $isValidIp = filter_var($host, \FILTER_VALIDATE_IP) !== false;

        if (!$isValidIp && preg_match(self::HOST_NAME_PATTERN, $host) !== 1) {
            throw new HttpException('Malformed Origin host.');
        }
    }
}
