<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The export family's knob census: the eight knobs the family
 * owns, and the rejection an unknown key in the family map answers
 * with -- following the correlation family's own {@see
 * CorrelationKnobs} lineage, which followed the tracing family's
 * ({@see TracingKnobs}), over the shared {@see StrictKnobs} census
 * the auth family widened to the full dotted path.
 *
 * The known list is spelled out in the rejection for the same
 * reason every family's census spells it: a `retry_delay`
 * typo must never fall through to the default posture unnoticed
 * -- the operator believes the backoff is widened while the lane
 * ships the clamped default, which is a bound the operator
 * believes is set while it is not.
 */
final class ExportKnobs
{
    /**
     * The knobs the family owns, in declaration order.
     */
    private const array KNOWN = [
        'enabled',
        'endpoint',
        'timeout_ms',
        'service_name',
        'max_retries',
        'retry_delay_ms',
        'retry_delay_cap_ms',
        'flush_per_request',
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
        StrictKnobs::rejectUnknown($map, 'observability.export', self::KNOWN);
    }
}
