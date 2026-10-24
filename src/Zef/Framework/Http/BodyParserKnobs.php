<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The body-parser family's knob census: the two knobs the family
 * owns, and the rejection an unknown key in the family map answers
 * with -- following the compression family's own
 * {@see CompressionKnobs} lineage over the shared {@see StrictKnobs}
 * census the whole config-driven lineage reads.
 *
 * The known list is spelled out in the rejection: a `json_deph` typo
 * must never fall through to the default posture unnoticed -- the
 * operator believes their depth ceiling is live while the default's
 * is, and a deployment that thinks it accepts documents nested to a
 * hundred levels is silently refusing them at thirty-two.
 */
final class BodyParserKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'json_depth',
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
        StrictKnobs::rejectUnknown($map, 'http.body_parser', self::KNOWN);
    }
}
