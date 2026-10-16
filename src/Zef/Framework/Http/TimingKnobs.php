<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The timing family's knob census: the two knobs the family owns,
 * and the rejection an unknown key in the family map answers with --
 * following the auth family's own {@see AuthKnobs} lineage, which
 * followed the CSRF family's, over the shared {@see StrictKnobs}
 * census the auth family widened to the full dotted path so the
 * observability campaign's families land on the same shape.
 *
 * The known list is spelled out in the rejection: an `emmit_header`
 * typo must never fall through to the default posture unnoticed --
 * the operator believes the header is disclosed while it is not.
 */
final class TimingKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'emit_header',
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
        StrictKnobs::rejectUnknown($map, 'observability.timing', self::KNOWN);
    }
}
