<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The correlation family's knob census: the two knobs the family
 * owns, and the rejection an unknown key in the family map answers
 * with -- following the tracing family's own {@see TracingKnobs}
 * lineage over the shared {@see StrictKnobs} census the auth
 * family widened to the full dotted path.
 *
 * The known list is spelled out in the rejection: an
 * `echo_traceparnt` typo must never fall through to the silent
 * posture unnoticed -- the operator believes the echo is armed
 * while the family ships it off, which is a disclosure the operator
 * believes is made while it is not.
 */
final class CorrelationKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'echo_traceparent',
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
        StrictKnobs::rejectUnknown($map, 'observability.correlation', self::KNOWN);
    }
}
