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
    public static function packed(string $address): false|string
    {
        $binary = inet_pton($address);

        if ($binary === false) {
            return false;
        }

        // Byte semantics throughout, exactly like the containment
        // arithmetic's own comparison ({@see MaskedPrefix}): a packed
        // form's bytes routinely compose multibyte or invalid UTF-8
        // sequences, so the mapped-range test is one byte comparison --
        // ten zero bytes then ff ff (RFC 4291 section 2.5.5.2) -- over a
        // sixteen-byte packing, never a character-counted slice. A
        // four-byte packing (a plain IPv4 address) has no sixteenth
        // byte to offer the fold.
        if (!isset($binary[15]) || substr_compare($binary, "\0\0\0\0\0\0\0\0\0\0\xff\xff", 0, 12) !== 0) {
            return $binary;
        }

        return self::folded($binary);
    }

    /**
     * The mapped packing's embedded IPv4 bytes -- or the packing
     * itself when the fold cannot read it (a sixteen-byte string is
     * always foldable in practice; the guard keeps the contract
     * honest).
     */
    private static function folded(string $binary): string
    {
        $folded = unpack('a12head/a4embedded', $binary);

        if (\is_array($folded) && isset($folded['embedded']) && \is_string($folded['embedded'])) {
            return $folded['embedded'];
        }

        return $binary;
    }
}
