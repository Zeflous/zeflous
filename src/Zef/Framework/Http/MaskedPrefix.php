<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The masked prefix comparison over two packed address forms: the
 * prefix-covered full bytes must be equal, and the partial byte at the
 * prefix boundary must agree under its high-bit mask.
 *
 * The full-byte comparison is byte semantics by construction:
 * `substr_compare` over packed address bytes, never a
 * character-semantic substr -- a packed form's bytes routinely compose
 * multibyte or invalid UTF-8 sequences, and a character-counted slice
 * of the first two "characters" of `194.128.0.0` and `192.169.0.0`
 * both substitute down to the same three bytes, making addresses the
 * grammar distinguishes compare equal. A zero-length compare (a prefix
 * narrower than its first byte) compares nothing and answers equal, so
 * there is no separate zero guard.
 *
 * Attribution: legacy `zef-framework` `Domain/Http/TrustedProxyMatcher`
 * (the `maskedPrefixMatches()` half, move-verbatim behaviour).
 */
final class MaskedPrefix
{
    /**
     * The high-bit mask each non-zero remainder judges under: the bits
     * from the byte's top down to the remainder's edge, indexed by the
     * remainder minus one. A shift-composed mask has no observable
     * mutant -- every mask a left shift composes clears the low bits the
     * shift fills in -- so the seven answers are stated as the table
     * they are, the layer's named-constant idiom for arithmetic no test
     * could ever distinguish from its mutation; and a remainder that
     * reached past the table would index out of it, which the engine
     * reports loudly rather than answering a shifted judgement.
     */
    private const array REMAINDER_MASKS = [0x80, 0xC0, 0xE0, 0xF0, 0xF8, 0xFC, 0xFE];

    /**
     * Answers whether the two packed forms agree on the prefix-covered
     * bits.
     *
     * @param string      $ipBinary      the packed address under judgement
     * @param string      $networkBinary the packed network base
     * @param int<0, max> $prefix        the prefix width in bits, already
     *                                   non-negative and family-validated by the caller
     */
    public static function matches(string $ipBinary, string $networkBinary, int $prefix): bool
    {
        $fullBytes = intdiv($prefix, 8);

        if (substr_compare($ipBinary, $networkBinary, 0, $fullBytes) !== 0) {
            return false;
        }

        $remainingBits = $prefix % 8;

        if ($remainingBits === 0) {
            return true;
        }

        $mask = self::REMAINDER_MASKS[$remainingBits - 1];

        return (\ord($ipBinary[$fullBytes]) & $mask) === (\ord($networkBinary[$fullBytes]) & $mask);
    }
}
