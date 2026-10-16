<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The telemetry hygiene unit: the single census and clamp every
 * recording point and delivery lane reads -- the family the
 * campaign's hygiene-and-export lane lands, exactly where the
 * tracing core's own porting notes reserved the arrival ({@see
 * SpanRecording}'s recording points, {@see Span}'s deviation
 * note).
 *
 * The hygiene is structural, not a knob: a sensitive key is never
 * recorded, a recorded value never carries control bytes or more
 * than its budget of payload, and a free-form diagnostic string
 * never carries credential material out of the process. There is
 * no configuration surface because there is no honest deployment
 * posture in which the collection carries credential material --
 * the auth boundary refuses it outright ({@see
 * \Zef\Framework\Http\AuthAttributes}), the correlation table
 * digests it into pointers ({@see CorrelationRedaction}), and the
 * recording surface drops it before it exists. The three censuses
 * are this class's own: the key fragments ({@see
 * self::isSensitiveKey()}), the pair vocabulary ({@see
 * self::redact()}) and the value budget ({@see self::string()}).
 *
 * The key census normalises before it judges -- underscores and
 * spaces fold to the hyphen, the fold is the multi-byte one the
 * repository mandates -- and the comparison is exact-or-contains
 * over the fragment list, the reference's own containment
 * semantics (a `db_password` carries the fragment `password`; so
 * does a `x-password-y`). The kelvin sign folds onto the ASCII k
 * through the case map alone, the one spelling whose fragment the
 * byte-level fold cannot read, and the suite pins it.
 *
 * Ported from the reference implementation (legacy
 * `zef-framework-src` `Application/Observability/TelemetrySanitizer.php`),
 * the evolved variant the monolith's own bug log shaped: the
 * UTF-8 scrub (invalid sequences made the exporter's JSON encode
 * throw and whole batches drop), the NaN/INF string forms (the
 * JSON encode refused them the same way), and the pair-redaction
 * widening (quoted JSON-style pairs passed through unredacted --
 * the reference's issue P-21). The port's own deviations: the
 * reference's `function_exists` feature probes are dropped (the
 * multi-byte mandate the repository already carries makes them
 * dead branches), the byte-level fold becomes the mandated
 * multi-byte one, and two dead census spellings are dropped --
 * the key census normalises the haystack's underscores to
 * hyphens before the containment, so the reference's own
 * underscore-spelled fragments (`api_key` and its siblings)
 * could never match anything, and the bare-cut fallback cuts at
 * a character boundary so even a too-narrow budget answers valid
 * UTF-8.
 */
final class TelemetrySanitizer
{
    /**
     * The key fragments a recording refuses outright: the
     * reference's own census, as the normalised comparison reads
     * them.
     */
    private const array SENSITIVE_KEY_FRAGMENTS = [
        'authorization',
        'cookie',
        'set-cookie',
        'password',
        'passwd',
        'token',
        'secret',
        'api-key',
        'apikey',
        'access-token',
        'refresh-token',
        'client-secret',
    ];

    /**
     * The pair vocabulary a free-form string's redaction matches:
     * the lowercase key suffixes whose `key: value` and
     * `key=value` pairs are scrubbed, mirroring the pair matcher's
     * own keyword list.
     */
    private const array REDACT_PAIR_KEYS = [
        'password',
        'passwd',
        'pwd',
        'passphrase',
        'secret',
        'token',
        'authorization',
        'credentials',
        'credential',
        'apikey',
        'api-key',
        'api_key',
        'clientsecret',
        'client-secret',
        'client_secret',
        'accesstoken',
        'access-token',
        'access_token',
        'refreshtoken',
        'refresh-token',
        'refresh_token',
        'privatekey',
        'private-key',
        'private_key',
    ];

    /**
     * The pair shape a redaction consumes: a key that may be
     * quoted, a separator, and a value that may be quoted -- the
     * widened grammar the reference's P-21 finding demanded, so a
     * JSON-style `"password":"x"` line scrubs the same way a bare
     * `password=x` one does.
     *
     * The value stops at the separators a diagnostic's own
     * structure carries: the whitespace, comma and semicolon of a
     * prose line, the quotes that close a quoted arm -- and the
     * `?`, `&` and `)` of the URLs and call traces an engine
     * diagnostic embeds. Without those three, one earlier pair's
     * value swallows every later pair that rides the same URL: the
     * `http:` a refused transport's warning spells would consume
     * `//host/v1/traces?token=secret` as its own value, and the
     * credential-bearing pair the vocabulary exists to scrub would
     * ride the diagnostic out unredacted, hidden inside a pair
     * nobody judged sensitive.
     */
    private const string PAIR_PATTERN
        = '/([\'"]?)(\w+(?:[-_]\w+)*)\1\s*[:=]\s*([\'"]?)([^\s,;\'"?&)]+)\3/iu';

    /**
     * Answers whether a key is sensitive cargo a recording must
     * refuse: the normalised containment over the fragment census.
     */
    public static function isSensitiveKey(string $key): bool
    {
        $normalized = mb_strtolower(str_replace(['_', ' '], '-', $key));

        return array_any(
            self::SENSITIVE_KEY_FRAGMENTS,
            static fn (string $fragment): bool => str_contains($normalized, $fragment),
        );
    }

    /**
     * Clamps a string to its payload budget: the control bytes the
     * protocols refuse are stripped, invalid UTF-8 is scrubbed to
     * the substitution character, and a value longer than the
     * budget truncates at a character boundary under the budget
     * with the ellipsis marking the cut.
     */
    public static function string(string $value, int $limit = 2048): string
    {
        if ($limit < 1) {
            return '';
        }

        $value = self::scrubbed(
            preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '',
        );

        if (mb_strlen($value, '8bit') <= $limit) {
            return $value;
        }

        return self::truncated($value, $limit);
    }

    /**
     * Redacts credential material out of a free-form diagnostic
     * string: the pair vocabulary's `key: value` and `key=value`
     * pairs, the RFC 6750 bearer token -- then the clamping every
     * string the telemetry lanes carry answers through. This is
     * the redaction the transport's own failure diagnostics read
     * ({@see OtlpHttpJsonExporter}), because an engine error
     * message is a diagnostic string like any other.
     */
    public static function redact(string $value, int $limit = 2048): string
    {
        $value = self::scrubbed($value);
        $value = self::pairsRedacted($value);
        $value = preg_replace(
            '/\bBearer\s+[a-z0-9._~+\/-]+=*/iu',
            'Bearer [REDACTED]',
            $value,
        ) ?? $value;

        return self::string($value, $limit);
    }

    /**
     * Sanitises one recorded value: strings clamp, the scalar
     * identities pass, floats answer their JSON-safe forms (the
     * NaN and infinities the encoder's own grammar refuses become
     * their string spellings), arrays walk under their entry
     * budget with sensitive entries marked, and anything else
     * answers the debug type -- a value the lanes cannot carry is
     * named, never serialized.
     */
    public static function value(mixed $value): mixed
    {
        return match (true) {
            \is_string($value) => self::string($value),
            \is_int($value), \is_bool($value), $value === null => $value,
            \is_float($value) => self::floatValue($value),
            \is_array($value) => self::arrayValue($value),
            default => get_debug_type($value),
        };
    }

    /**
     * Sanitises an attribute map: the sensitive keys are dropped
     * outright -- refused, not marked, because a recorded
     * credential never exists to be redacted -- and every kept
     * value answers the value sanitisation.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    public static function attributes(array $attributes): array
    {
        $sanitized = [];

        foreach ($attributes as $key => $value) {
            if (self::isSensitiveKey($key)) {
                continue;
            }

            $sanitized[$key] = self::value($value);
        }

        return $sanitized;
    }

    /**
     * Scrubs invalid UTF-8: the encoder's JSON grammar throws on
     * raw byte sequences, and a diagnostic that cannot encode
     * takes the whole delivery batch with it -- the bytes become
     * the substitution character instead.
     */
    private static function scrubbed(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_scrub($value, 'UTF-8');
    }

    /**
     * Truncates over-budget bytes to the character boundary just
     * under the budget, the ellipsis marking the cut; a budget too
     * narrow to carry the marker truncates bare, because a
     * negative-length cut would keep bytes from the end and bypass
     * the budget entirely.
     */
    private static function truncated(string $value, int $limit): string
    {
        if ($limit <= 3) {
            return mb_strcut($value, 0, $limit, 'UTF-8');
        }

        return mb_strcut($value, 0, $limit - 3, 'UTF-8') . '…';
    }

    /**
     * Redacts the pair vocabulary's entries: the staged callback
     * over one flat pattern, the keyword vocabulary living in PHP
     * so the regex stays simple while the match semantics -- the
     * keyword as a suffix of the word before the separator --
     * carry over.
     */
    private static function pairsRedacted(string $value): string
    {
        $redacted = preg_replace_callback(
            self::PAIR_PATTERN,
            static fn (array $match): string => self::isPairKey($match[2])
                ? $match[2] . '=[REDACTED]'
                : $match[0],
            $value,
        );

        return $redacted ?? $value;
    }

    /**
     * Answers whether a pair's key word ends with a vocabulary
     * entry: the lowercase suffix comparison.
     */
    private static function isPairKey(string $word): bool
    {
        $needle = mb_strtolower($word);

        return array_any(
            self::REDACT_PAIR_KEYS,
            static fn (string $suffix): bool => str_ends_with($needle, $suffix),
        );
    }

    /**
     * Answers the float's JSON-safe form: the NaN and infinities
     * the encoder's grammar refuses -- and a bare cast would
     * spell into an engine warning -- answer their literal string
     * forms instead.
     */
    private static function floatValue(float $value): float|string
    {
        if (is_nan($value)) {
            return 'NAN';
        }

        if (is_infinite($value)) {
            return $value === \INF ? 'INF' : '-INF';
        }

        return $value;
    }

    /**
     * Walks an array value under its entry budget: the first
     * thirty-two entries sanitise, sensitive keys answer the
     * marker (a nested credential is marked, because the walk's
     * own key was not the sensitive one the outer map refused),
     * and the rest drop -- a recorded collection is bounded like
     * every other telemetry cargo.
     *
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function arrayValue(array $value): array
    {
        $sanitized = [];

        foreach (\array_slice($value, 0, 32, true) as $key => $entry) {
            $name = (string) $key;
            $sanitized[$name] = self::isSensitiveKey($name)
                ? '[REDACTED]'
                : self::value($entry);
        }

        return $sanitized;
    }
}
