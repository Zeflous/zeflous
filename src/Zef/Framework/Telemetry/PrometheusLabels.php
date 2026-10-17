<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The exposition's label grammar: the label block one series renders
 * -- the unit the {@see PrometheusRenderer} reads, extracted whole so
 * the series' identity and the labels' own alphabet stay one named
 * place each.
 *
 * THE ALPHABETS ARE THE FORMAT'S OWN: the label name sanitises into
 * `[a-zA-Z0-9_]` (everything else folding to the underscore), a name
 * that does not start alphabetic -- or the empty name -- prefixed
 * onto the `lbl_` stem, because a label name the exposition grammar
 * refuses is a scrape the consumer rejects wholesale, never a label
 * silently dropped. The label value escapes the three characters the
 * format carries (the backslash, the double quote, the line feed).
 *
 * THE BLOCK IS THE MERGED PAIR SET: the static labels the render
 * carries on every series, then the series' scalar dimensions (a
 * non-scalar dimension is dropped, never stringified blindly), the
 * pairs ordered by name -- a series with no pairs omits the `{...}`
 * block entirely.
 */
final class PrometheusLabels
{
    /**
     * The sanitised name an empty or unusable label name answers.
     */
    private const string LABEL_NAME_DEFAULT = 'zef_unnamed_label';

    /**
     * The label block for one series: the static labels and the
     * series' scalar dimensions, the names sanitised into the label
     * alphabet, the values escaped per the exposition format, the
     * pairs ordered by name -- a series with no pairs omits the
     * `{...}` block entirely.
     *
     * @param array<mixed,mixed>   $attributes   the series' dimension map
     * @param array<string,string> $staticLabels the label pairs every series carries
     */
    public static function block(array $attributes, array $staticLabels): string
    {
        $pairs = [];
        foreach ($staticLabels as $name => $value) {
            $pairs[self::sanitizedName($name)] = $value;
        }

        foreach ($attributes as $name => $value) {
            if (!\is_string($name) || $name === '') {
                continue;
            }

            if (!\is_scalar($value)) {
                // Non-scalar dimensions are dropped, never stringified blindly.
                continue;
            }

            $pairs[self::sanitizedName($name)] = (string) $value;
        }

        if ($pairs === []) {
            return '';
        }

        ksort($pairs);
        $encoded = [];
        foreach ($pairs as $name => $value) {
            $encoded[] = \sprintf('%s="%s"', $name, self::escapedValue($value));
        }

        return '{' . implode(',', $encoded) . '}';
    }

    /**
     * The label name sanitised into the exposition's own alphabet:
     * the word characters kept, everything else folded to the
     * underscore, a name that does not start alphabetic prefixed
     * onto the `lbl_` stem.
     */
    private static function sanitizedName(string $name): string
    {
        $clean = preg_replace('/\W/', '_', $name);
        $clean = \is_string($clean) ? $clean : self::LABEL_NAME_DEFAULT;
        if ($clean === '' || preg_match('/^[[:alpha:]_]/', $clean) !== 1) {
            return 'lbl_' . $clean;
        }

        return $clean;
    }

    /**
     * The label value escaped per the exposition format: the
     * backslash, the double quote and the line feed.
     */
    private static function escapedValue(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\"', '\n'], $value);
    }
}
