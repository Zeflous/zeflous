<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The recorded float's JSON-safe form: the unit that owns the
 * answer one float value gives the delivery lanes -- the NaN and
 * the infinities the encoder's own grammar refuses (and a bare
 * cast would spell into an engine warning) answer their literal
 * string forms instead, so the value never takes the whole
 * delivery batch down with it when the wire encode runs.
 *
 * The recording-side spelling this unit owns differs from the
 * exposition's own on purpose ({@see PrometheusNumbers} answers
 * the format's `NaN`/`+Inf`/`-Inf` literals; this unit answers the
 * `NAN`/`INF`/`-INF` spellings the JSON grammar's consumers read):
 * two lanes, two grammars, one named place each.
 */
final class TelemetryFloatForm
{
    /**
     * The float's JSON-safe form.
     */
    public static function value(float $number): float|string
    {
        if (is_nan($number)) {
            return 'NAN';
        }

        if (is_infinite($number)) {
            return $number === \INF ? 'INF' : '-INF';
        }

        return $number;
    }
}
