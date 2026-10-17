<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The exposition's number grammar: the sample value's rendered form
 * and the divergence judgement the histogram members ride on -- the
 * unit the {@see PrometheusRenderer} reads, the format's own literal
 * spellings for the values the JSON grammar refuses.
 *
 * The NaN and the infinities answer their literal spellings (`NaN`,
 * `+Inf`, `-Inf`) exactly as the exposition format spells them, and
 * a finite value renders its JSON form with the zero fraction
 * preserved (`0.0`, never the bare `0` a zero float would otherwise
 * print) -- the float shape the series accumulated, kept through the
 * render.
 *
 * THE DIVERGENCE JUDGEMENT CARRIES THE NaN CONTRACT EXPLICITLY: with
 * a NaN sum (or count) every float comparison is false, so a NaN
 * series renders count-only -- the historical contract -- and the
 * explicit `is_nan` guards keep that contract under either spelling
 * the guard block reads in, the trap the reference's own comment
 * documented for the inverted form (a bare `<= epsilon` return
 * would flip NaN to answer `_sum = NaN`, the value the exposition's
 * own consumers refuse).
 */
final class PrometheusNumbers
{
    /**
     * The sample value's rendered form.
     */
    public static function rendered(float $value): string
    {
        if (!is_finite($value)) {
            if (is_nan($value)) {
                return 'NaN';
            }

            return $value > 0 ? '+Inf' : '-Inf';
        }

        $formatted = json_encode($value, \JSON_PRESERVE_ZERO_FRACTION);

        return \is_string($formatted) ? $formatted : '0.0';
    }

    /**
     * Answers whether an observation series has diverged -- its sum
     * no longer in step with its count, the classic histogram
     * members' own condition -- carrying the NaN contract: a NaN sum
     * or count never diverges, so the series renders count-only.
     */
    public static function diverged(float $count, float $sum): bool
    {
        if (is_nan($sum) || is_nan($count)) {
            return false;
        }

        return abs($sum - $count) > \PHP_FLOAT_EPSILON;
    }
}
