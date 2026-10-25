<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The slow-request family's knob census: the two knobs the family
 * owns, and the rejection an unknown key in the family map answers
 * with -- the timing family's own {@see TimingKnobs} shape, over
 * the shared {@see StrictKnobs} census every observability family
 * lands on.
 *
 * The known list is spelled out in the rejection: a `threshold`
 * typo (the unit-less spelling) must never fall through to the
 * default posture unnoticed -- the operator believes the bar sits
 * at their written value while every request is judged against the
 * default corridor's own round number instead.
 */
final class SlowRequestKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'threshold_ms',
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
        StrictKnobs::rejectUnknown($map, 'observability.slow_request', self::KNOWN);
    }
}
