<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CIDR containment arithmetic: does an address fall inside a network
 * prefix?
 *
 * Addresses are compared through their canonical packed binary forms
 * ({@see Ipv4MappedForm::packed()}: `inet_pton()` with the IPv4-mapped
 * IPv6 spelling folded to its embedded IPv4 packing, answering false
 * for an unrecognised address without warning -- verified against the
 * engine -- so the soft-false semantics need no separate validity
 * guard), which makes containment a byte-prefix comparison delegated to
 * the {@see MaskedPrefix} collaborator. An IPv4 address and an IPv6
 * network are different address families and never contain each other
 * -- after the fold the canonical packing's width is the family (four
 * bytes is IPv4, sixteen is IPv6), and the prefix must fit that width
 * (32 for IPv4, 128 for IPv6).
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
        $ipBinary = Ipv4MappedForm::packed($ip);
        $networkBinary = Ipv4MappedForm::packed($network);

        // Two ways the addresses cannot relate: one of them packs nothing,
        // or they belong to different address families. Both answer the
        // same refusal.
        if (
            $ipBinary === false
            || $networkBinary === false
            || self::widthOf($ipBinary) !== self::widthOf($networkBinary)
        ) {
            return false;
        }

        if ($prefix < 0 || $prefix > self::widthOf($ipBinary)) {
            return false;
        }

        return MaskedPrefix::matches($ipBinary, $networkBinary, $prefix);
    }

    /**
     * The canonical packing's width in bits: a sixteen-byte packing is
     * the IPv6 family (128), a four-byte one IPv4 (32) -- read from the
     * packing's own bytes, since inet_pton() answers exactly these two
     * widths, never a character-counted length whose multibyte
     * semantics a packed form's bytes routinely defeat (the same
     * discipline the byte-prefix comparison itself carries).
     */
    private static function widthOf(string $binary): int
    {
        // @infection-ignore-all inet_pton answers only four-byte or
        // sixteen-byte packings -- never fifteen -- so the sixteenth-byte
        // presence probe agrees with a fifteenth-byte probe on every
        // packing the engine can produce; a width-divergent packing
        // never exists.
        $sixteenBytePacking = isset($binary[15]);

        return $sixteenBytePacking ? 128 : 32;
    }
}
