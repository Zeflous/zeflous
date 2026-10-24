<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The compression family's knob census: the three knobs the family
 * owns, and the rejection an unknown key in the family map answers
 * with -- following the timing family's own {@see TimingKnobs}
 * lineage, which followed the auth family's, over the shared
 * {@see StrictKnobs} census the whole config-driven lineage reads.
 *
 * The known list is spelled out in the rejection: a `minumum_bytes`
 * typo must never fall through to the default posture unnoticed --
 * the operator believes their threshold is live while the default's
 * is, and a deployment that thinks it compresses nothing over 4 KB
 * is silently compressing nothing over 1 KB.
 */
final class CompressionKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'minimum_bytes',
        'level',
    ];

    /**
     * Rejects the map's unknown keys with the known list spelled out.
     *
     * @param array<array-key, mixed> $map the family's knob map
     *
     * @throws HttpException when the map carries an unknown key
     */
    public static function rejectUnknown(array $map): void
    {
        StrictKnobs::rejectUnknown($map, 'http.compression', self::KNOWN);
    }
}
