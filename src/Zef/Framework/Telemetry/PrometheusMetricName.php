<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The exposition's metric-name grammar: the sanitised stem a series'
 * name renders under -- the unit the {@see PrometheusSeries}
 * identity's own half reads, mirroring the label alphabet {@see
 * PrometheusLabels} carries.
 *
 * THE ALPHABET IS THE FORMAT'S OWN: the `[a-zA-Z0-9_:]` characters
 * kept, everything else folding to the underscore; a name that does
 * not start alphabetic (or with the underscore or colon the grammar
 * allows) prefixed onto the `zef_` stem; an empty or unusable name
 * answered by the default -- because a metric name the exposition
 * grammar refuses is a scrape the consumer rejects wholesale, never
 * a series silently dropped.
 */
final class PrometheusMetricName
{
    /**
     * The sanitised name an empty or unusable metric name answers.
     */
    private const string METRIC_NAME_DEFAULT = 'zef_unnamed_metric';

    /**
     * The metric name sanitised into the exposition's own alphabet.
     */
    public static function sanitized(string $name): string
    {
        $name = mb_trim($name);
        if ($name === '') {
            return self::METRIC_NAME_DEFAULT;
        }

        $clean = preg_replace('/[^\w:]/', '_', $name);
        $clean = \is_string($clean) ? $clean : self::METRIC_NAME_DEFAULT;
        if (preg_match('/^[[:alpha:]_:]/', $clean) !== 1) {
            return 'zef_' . $clean;
        }

        return $clean;
    }
}
