<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The OTLP metrics lane's key split: the composite snapshot key
 * ({@see MeterSlot}'s `name . '|' . json` grammar) read back into
 * the metric NAME the series was recorded under -- the fold the
 * JSON payload composes so the wire carries the name the caller
 * spelled ({@see OtlpMetricsPayload}), never the composite key
 * whole (the issue #779 finding: a series recorded as
 * `zef.http.errors.total` under `["http.request.method" => "GET"]`
 * shipped as ONE garbage metric per series, its name the whole
 * `name|{...}` composite).
 *
 * The split is the exposition's own ({@see PrometheusSeries}, the
 * Prometheus lane's twin fold): the FIRST separator the grammar
 * mints, so a name carrying a pipe of its own reads the same
 * off-vocabulary truncation both delivery lanes answer -- the
 * framework-owned `zef.*` names never carry one, and the split's
 * one grammar is the grammar the key itself was minted by. A key
 * with no separator reads whole: the hand-made maps the suite
 * composes carry bare names with the attributes on the series.
 */
final class OtlpMetricKey
{
    /**
     * The metric name one composite snapshot key was minted from:
     * the part before the first separator, or the whole key when
     * it carries none.
     */
    public static function name(string $key): string
    {
        $pipe = mb_strpos($key, '|');
        if ($pipe === false) {
            return $key;
        }

        return mb_substr($key, 0, $pipe);
    }
}
