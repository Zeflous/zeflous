<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The tracing family's knob census: the three knobs the family
 * owns, and the rejection an unknown key in the family map answers
 * with -- following the timing family's own {@see TimingKnobs}
 * lineage over the shared {@see StrictKnobs} census the auth
 * family widened to the full dotted path.
 *
 * The known list is spelled out in the rejection: a `max_queu`
 * typo must never fall through to the default posture unnoticed --
 * the operator believes the bound is 512 while the queue ships the
 * 2048 default, which is a limit the operator believes is set
 * while it is not.
 */
final class TracingKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'max_queue',
        'batch_size',
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
        StrictKnobs::rejectUnknown($map, 'observability.tracing', self::KNOWN);
    }
}
