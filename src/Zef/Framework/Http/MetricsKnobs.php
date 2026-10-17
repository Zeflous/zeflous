<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The metrics family's knob census: the four knobs the family owns,
 * and the rejection an unknown key in the family map answers with --
 * following the export family's own {@see ExportKnobs} lineage, over
 * the shared {@see StrictKnobs} census the auth family widened to
 * the full dotted path.
 *
 * The known list is spelled out in the rejection for the same reason
 * every family's census spells it: a `timeout` typo must never fall
 * through to the default posture unnoticed -- the operator believes
 * the scrape is bounded while the family ships the clamped default,
 * which is a bound the operator believes is set while it is not.
 */
final class MetricsKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'metrics_path',
        'health_path',
        'timeout_ms',
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
        StrictKnobs::rejectUnknown($map, 'observability.metrics', self::KNOWN);
    }
}
