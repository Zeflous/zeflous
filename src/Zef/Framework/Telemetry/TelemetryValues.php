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
            \is_float($value) => TelemetryFloatForm::value($value),
            \is_array($value) => self::arrayValue($value, $depth),
            default => get_debug_type($value),
        };
    }

    /**
     * Sanitises an attribute map: the sensitive keys are dropped
     * outright -- refused, not marked, because a recorded credential
     * never exists to be redacted (the judgement reads the caller's
     * own spelling, never the budgeted fold: a credential key
     * truncated under its fragment is still a credential key) -- and
     * every kept value answers the value sanitisation under its own
     * budgeted key ({@see TelemetrySanitizer::key()}). The declared
     * key union is the walk's own honest range -- PHP's own array
     * grammar coerces a numeric string key to the int it spells, and
     * a host's array literal can carry one -- the census coerces back
     * to the string every identity grammar renders.
     *
     * @param array<array-key, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    public static function attributes(array $attributes): array
    {
        $sanitized = [];

        foreach ($attributes as $key => $value) {
            $name = TelemetrySanitizer::key((string) $key);

            if ($name === '' || TelemetrySanitizer::isSensitiveKey((string) $key)) {
                continue;
            }

            $sanitized[$name] = self::value($value);
        }

        return $sanitized;
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
            $name = TelemetrySanitizer::key((string) $key);

            if ($name === '') {
                continue;
            }

            if (TelemetrySanitizer::isSensitiveKey((string) $key)) {
                $walked[$name] = '[REDACTED]';

                continue;
            }

            $walked[$name] = self::value($value[$key], $depth + 1);
        }

        return $walked;
    }
}
