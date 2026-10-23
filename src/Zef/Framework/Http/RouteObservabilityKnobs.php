<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The router observability family's knob census: the two knobs the
 * family owns, and the rejection an unknown key in the family map
 * answers with -- following the metrics family's own {@see
 * MetricsKnobs} lineage over the shared {@see StrictKnobs} census
 * the auth family widened to the full dotted path.
 *
 * The known list is spelled out in the rejection for the same reason
 * every family's census spells it: a `debug_matsh` typo must never
 * fall through to the silent posture unnoticed -- the operator
 * believes the development header is armed while the family ships
 * it off, which is a disclosure the operator believes is made while
 * it is not.
 */
final class RouteObservabilityKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'debug_match',
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
        StrictKnobs::rejectUnknown($map, 'observability.router', self::KNOWN);
    }
}
