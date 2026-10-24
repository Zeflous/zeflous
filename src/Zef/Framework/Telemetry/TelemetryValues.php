<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The recorded-value walk: the shape one attribute or event value
 * answers before the delivery lanes carry it ({@see
 * TelemetrySanitizer} keeps the string and key censuses the walk
 * reads).
 *
 * Strings clamp through the string census, the scalar identities
 * pass, floats answer their JSON-safe forms (the NaN and infinities
 * the encoder's own grammar refuses become their string spellings),
 * arrays walk under their entry budget with sensitive entries
 * marked, and anything else answers the debug type -- a value the
 * lanes cannot carry is named, never serialized. The walk is
 * depth-bounded the way the logging sibling's is ({@see
 * \Zef\Framework\Logging\LogContextSanitizer}): a deeply-nested or
 * self-referential attribute array answers the truncation marker at
 * the bound instead of exhausting the stack inside the request path.
 */
final class TelemetryValues
{
    /** The attribute walk's depth bound -- the logging sibling's own cap, mirrored. */
    private const int MAX_DEPTH = 5;

    /** The array value's entry budget: the first entries sanitise, the rest drop. */
    private const int ENTRY_BUDGET = 32;

    /** The truncation marker every budgeted fold answers. */
    private const string TRUNCATED = '_truncated';

    /**
     * Sanitises one recorded value.
     *
     * The declared union is the walk's own honest range -- exactly what
     * the match arms answer and nothing wider -- so the analysers can
     * verify every member that leaves the fold.
     *
     * @return null|array<array-key, mixed>|bool|float|int|string
     */
    public static function value(mixed $value, int $depth = 0): array|bool|float|int|string|null
    {
        return match (true) {
            \is_string($value) => TelemetrySanitizer::string($value),
            \is_int($value), \is_bool($value), $value === null => $value,
            \is_float($value) => self::floatValue($value),
            \is_array($value) => self::arrayValue($value, $depth),
            default => get_debug_type($value),
        };
    }

    /**
     * Sanitises an attribute map: the sensitive keys are dropped
     * outright -- refused, not marked, because a recorded credential
     * never exists to be redacted -- and every kept value answers the
     * value sanitisation.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    public static function attributes(array $attributes): array
    {
        $sanitized = [];

        foreach (array_keys($attributes) as $key) {
            if (TelemetrySanitizer::isSensitiveKey($key)) {
                continue;
            }

            $sanitized[$key] = self::value($attributes[$key]);
        }

        return $sanitized;
    }

    /**
     * Answers the float's JSON-safe form: the NaN and infinities the
     * encoder's grammar refuses -- and a bare cast would spell into
     * an engine warning -- answer their literal string forms instead.
     */
    private static function floatValue(float $number): float|string
    {
        if (is_nan($number)) {
            return 'NAN';
        }

        if (is_infinite($number)) {
            return $number === \INF ? 'INF' : '-INF';
        }

        return $number;
    }

    /**
     * Walks an array value under its entry budget: the first
     * thirty-two entries sanitise, sensitive keys answer the marker
     * (a nested credential is marked, because the walk's own key was
     * not the sensitive one the outer map refused), and the rest drop
     * -- a recorded collection is bounded like every other telemetry
     * cargo.
     *
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function arrayValue(array $value, int $depth): array
    {
        if ($depth === self::MAX_DEPTH) {
            return [self::TRUNCATED => true];
        }

        $walked = [];

        foreach (array_keys(\array_slice($value, 0, self::ENTRY_BUDGET, true)) as $key) {
            $name = (string) $key;

            if (TelemetrySanitizer::isSensitiveKey($name)) {
                $walked[$name] = '[REDACTED]';

                continue;
            }

            $walked[$name] = self::value($value[$key], $depth + 1);
        }

        return $walked;
    }
}
