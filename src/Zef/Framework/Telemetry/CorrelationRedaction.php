<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The attribute table's redaction: the diagnostic view the causal
 * context's table answers -- the sensitive values as stable sha256
 * digests, everything else passed as given.
 *
 * The redaction is the correlation table's own posture, distinct
 * from the auth boundary's ({@see \Zef\Framework\Http\AuthAttributes}
 * REFUSES sensitive keys outright -- no credential material may
 * travel as labelled cargo): a causal diagnostic may point at the
 * material without carrying it, and the sha256 digest is the
 * pointer. The key census -- the fragments a redaction digests
 * rather than passes -- is the reference's own, as the lowercase
 * comparison reads them.
 *
 * The redaction is deterministic by design: the same sensitive
 * value answers the same digest every time, so two log lines that
 * correlate the same credential also correlate each other, and a
 * grep for the digest finds every line the value produced. A
 * random salt would break both -- the digest's whole value is that
 * it is a stable identity for a value nobody can read back. A
 * sensitive value that is not a string (a bool, a count) carries
 * no bytes to digest, and answers the `[REDACTED]` marker instead
 * -- the redaction never leaks the shape it refuses to read.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Observability/CorrelationContext.php`): the fragment
 * census, the digest shape and the marker move verbatim, in the
 * redaction's own unit.
 */
final class CorrelationRedaction
{
    /**
     * The key fragments a redaction digests rather than passes: the
     * reference's own census, as the lowercase comparison reads them.
     */
    private const array SENSITIVE_KEY_FRAGMENTS = [
        'authorization',
        'cookie',
        'token',
        'secret',
        'password',
        'credential',
        'body',
        'idempotency',
    ];

    /**
     * Answers the table's redacted view: correlation-safe diagnostics
     * only, the sensitive values as stable digests.
     *
     * @param array<string, null|bool|float|int|string> $attributes the
     *                                                              validated table
     *
     * @return array<string, null|bool|float|int|string> the redacted copy
     */
    public static function redacted(array $attributes): array
    {
        $out = [];

        foreach ($attributes as $key => $value) {
            $out[$key] = self::isSensitiveKey($key)
                ? (\is_string($value) ? 'sha256:' . hash('sha256', $value) : '[REDACTED]')
                : $value;
        }

        return $out;
    }

    /**
     * Whether a key spells sensitive material the redaction must
     * digest -- the fragment census as the lowercase comparison
     * reads it.
     */
    private static function isSensitiveKey(string $key): bool
    {
        $value = mb_strtolower($key);

        return array_any(self::SENSITIVE_KEY_FRAGMENTS, static fn (string $fragment): bool => str_contains($value, $fragment));
    }
}
