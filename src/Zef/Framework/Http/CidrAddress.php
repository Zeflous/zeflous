<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CIDR containment arithmetic: does an address fall inside a network
 * prefix?
 *
 * Addresses are compared through their packed binary forms
 * (`inet_pton()`, which answers false for an unrecognised address
 * without warning -- verified against the engine -- so the soft-false
 * semantics need no separate validity guard), which makes containment a
 * byte-prefix comparison delegated to the {@see MaskedPrefix}
 * collaborator. An IPv4 address and an IPv6 network are different
 * address families and never contain each other -- the family is read
 * from the address's own validated spelling (the IPv4 grammar filter),
 * and the prefix must fit its family's width (32 for IPv4, 128 for
 * IPv6). The family widths are the grammar's two answers rather than a
 * byte-length call whose encoding semantics no test could ever
 * distinguish (the stream engine's measurement battle, avoided by
 * construction).
 *
 * Attribution: legacy `zef-framework` `Domain/Http/TrustedProxyMatcher`
 * (the `ipInCidr()` half, move-verbatim behaviour).
 */
final class CidrAddress
{
    /**
     * Answers whether the address falls inside the network prefix.
     *
     * @param string $ip      the address under judgement
     * @param string $network the network base address
     * @param int    $prefix  the prefix width in bits
     */
    public static function contains(string $ip, string $network, int $prefix): bool
    {
        $ipBinary = inet_pton($ip);
        $networkBinary = inet_pton($network);

        // Two ways the addresses cannot relate: one of them packs nothing,
        // or they belong to different address families. Both answer the
        // same refusal.
        if ($ipBinary === false || $networkBinary === false || self::widthOf($ip) !== self::widthOf($network)) {
            return false;
        }

        if ($prefix < 0 || $prefix > self::widthOf($ip)) {
            return false;
        }

        return MaskedPrefix::matches($ipBinary, $networkBinary, $prefix);
    }

    /**
     * Answers the prefix width of the address's family: 32 bits for an
     * IPv4 address, 128 for an IPv6 one.
     *
     * The family is read from the address's own validated spelling -- the
     * IPv4 grammar filter -- because an address's family and its packed
     * form's length are the same fact stated twice.
     *
     * @param string $address the address whose family width is asked for
     *
     * @return 128|32 the family's prefix width in bits
     */
    private static function widthOf(string $address): int
    {
        return filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) === false ? 128 : 32;
    }
}
