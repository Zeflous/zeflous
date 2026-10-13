<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Answers whether an address is one of the trusted proxies.
 *
 * The trusted-proxy boundary the security slices own: a forwarded header
 * is honoured only when the peer that delivered the request is itself
 * trusted, so `X-Forwarded-*` spoofing by an arbitrary client never
 * crosses the ingress. One entry matches either as an exact address
 * (`127.0.0.1`) or as a CIDR network (`10.0.0.0/8`), IPv4 and IPv6
 * alike; the containment arithmetic lives in the {@see CidrAddress}
 * collaborator.
 *
 * The entry grammar is deliberately soft-false: an entry that is empty,
 * not a CIDR shape, or carries a malformed prefix simply does not match
 * -- a misconfigured list never turns into an exception an attacker can
 * probe with, and the address under judgement is validated before any
 * comparison runs so an unparseable input answers false rather than
 * warning.
 *
 * Attribution: legacy `zef-framework` `Domain/Http/TrustedProxyMatcher.php`
 * (move-verbatim behaviour; the shared CIDR-matching logic the reference
 * implementation extracted from its request factory and client-address
 * resolver).
 */
final class TrustedProxyMatcher
{
    /**
     * Answers whether the address is one of the trusted proxies.
     *
     * @param string       $ip      the peer address, typically `REMOTE_ADDR`
     * @param list<string> $trusted the trusted proxy entries (exact
     *                              addresses or CIDR networks)
     */
    public static function matches(string $ip, array $trusted): bool
    {
        $ip = mb_trim($ip);

        if (filter_var($ip, \FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return array_any($trusted, static fn (string $entry): bool => self::entryMatches($entry, $ip));
    }

    /**
     * Answers whether one entry claims the address: an exact string match
     * or CIDR containment. An empty entry matches nothing downstream --
     * neither the exact compare against a validated address nor the CIDR
     * shape -- so there is no separate emptiness guard.
     *
     * @param string $entry the raw trusted-list entry, whitespace-trimmed here
     * @param string $ip    the validated address under judgement
     */
    private static function entryMatches(string $entry, string $ip): bool
    {
        $entry = mb_trim($entry);

        if ($entry === $ip) {
            return true;
        }

        return self::cidrEntryMatches($entry, $ip);
    }

    /**
     * Answers whether a CIDR-shaped entry claims the address.
     *
     * The entry must carry exactly the `network/prefix` shape -- a slash,
     * then a pure-digit prefix; anything else (including no slash at all,
     * a trailing or a duplicated slash) is not a CIDR literal and does not
     * match: the missing prefix after the split answers the empty string,
     * which the digit grammar rejects.
     *
     * @param string $entry the raw trusted-list entry
     * @param string $ip    the validated address under judgement
     */
    private static function cidrEntryMatches(string $entry, string $ip): bool
    {
        $parts = explode('/', $entry, 2);
        $prefix = $parts[1] ?? '';

        if (!ctype_digit($prefix)) {
            return false;
        }

        return CidrAddress::contains($ip, $parts[0], (int) $prefix);
    }
}
