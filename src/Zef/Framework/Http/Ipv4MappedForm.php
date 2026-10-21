<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Folds the IPv4-mapped IPv6 spelling of an address to its embedded IPv4
 * packing, so the trust arithmetic judges peers by the host they denote
 * rather than by the socket spelling the peer arrived on.
 *
 * `::ffff:0:0/96` (RFC 4291 section 2.5.5.2) is the mapped range: a
 * dual-stack listener hands the application `::ffff:10.0.0.1` for a peer
 * whose address is `10.0.0.1`, and every comparison -- the
 * {@see TrustedProxyMatcher} exact entry, the {@see CidrAddress}
 * containment -- must treat the two spellings as the same host, or the
 * boundary silently refuses to trust a proxy it was told to trust (and
 * every client behind that proxy shares one rate-limit bucket). The
 * fold is one-way (a plain IPv4 spelling never expands) and covers only
 * the mapped range: the deprecated IPv4-compatible `::/96` spellings
 * keep their own family.
 */
final class Ipv4MappedForm
{
    /**
     * The address's packed binary form, with a mapped spelling folded to
     * the embedded IPv4 packing; false when the address packs nothing.
     */
    public static function packed(string $address): string|false
    {
        $binary = inet_pton($address);

        if ($binary === false) {
            return false;
        }

        if (\strlen($binary) === 16
            && substr($binary, 0, 10) === str_repeat("\0", 10)
            && substr($binary, 10, 2) === "\xff\xff"
        ) {
            return substr($binary, 12);
        }

        return $binary;
    }
}
