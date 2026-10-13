<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * One redaction candidate's judgement: whether a matched `key separator
 * value` region carries credential material, and the edit that masks it.
 *
 * The candidate grammar is ASCII by construction (byte-mode classes), so the
 * matched span's byte length is the same number `mb_strlen` would answer --
 * the one place this slice's MBString mutant is mathematically immortal (the
 * scoped ignore in `infection.json5` carries the proof; the neighbouring
 * `strcasecmp` forms stay under the mutator because they are not its
 * targets).
 *
 * Deviation from the reference, dead code removed: the legacy quote-stripping
 * block before the scheme-keyword check could never influence any observable
 * answer -- the check it fed is gated on the quote being empty, while the
 * strip only ran when the quote was not (verified by reachability analysis
 * over the two guards, and by `str_ends_with($x, '')` answering true for
 * every `$x`: the empty-quote case the outer guard excludes is the only one
 * the strip would have changed).
 *
 * Attribution: legacy `zef-framework` `Middleware/ErrorResponseFactory`
 * (the vocabulary constants and the edit construction, move-verbatim
 * behaviour; the `in_array(..., true)` memberships are restated as explicit
 * `strcasecmp` comparisons -- the strict flag is an immortal zone and the
 * explicit comparison is the house form).
 */
final class RedactionCandidate
{
    /**
     * Credential key tokens (compared case-insensitively) whose embedded
     * values must never reach a client or a log sink. Checked on the PHP
     * side so the candidate pattern stays flat.
     */
    private const array CREDENTIAL_KEYS = [
        'pass', 'password', 'passwd', 'pwd',
        'secret', 'token',
        'apikey', 'api_key', 'api-key',
        'authorization', 'bearer',
        'privatekey', 'private_key', 'private-key',
    ];

    /**
     * Auth scheme keywords: when the first value token of a credential key
     * is one of these ("Authorization: Bearer abc123xyz"), the credential
     * material continues past the whitespace ending the candidate match, so
     * the whole remaining region is consumed (legacy issue #307).
     */
    private const array AUTH_SCHEME_KEYWORDS = ['bearer', 'basic', 'digest'];

    /**
     * Builds the masking edit for one candidate match, or answers null when
     * the matched key is not a credential key.
     *
     * @param array<array-key, list{string, int}> $match one PREG_OFFSET_CAPTURE
     *                                                   match: the whole match at
     *                                                   index 0, the key, the
     *                                                   separator and the quote at
     *                                                   indices 1 to 3
     *
     * @return null|array{int, int, string} the edit -- byte offset, byte
     *                                      length and replacement text -- or
     *                                      null when the key carries no
     *                                      credential material
     */
    public static function edit(string $message, array $match): ?array
    {
        [$key, $separator, $quote] = [$match[1][0], $match[2][0], $match[3][0]];

        if (!self::isCredentialKey($key)) {
            return null;
        }

        $matchStart = $match[0][1];
        $end = $matchStart + mb_strlen($match[0][0]);

        // Only an unquoted scheme-keyword value extends: quoted regions
        // already end at their closing quote, and single-token values keep
        // the historical prose-preserving behaviour.
        $valueToken = mb_substr($match[0][0], mb_strlen($key) + mb_strlen($separator));

        if ($quote === '' && self::isAuthSchemeKeyword($valueToken)) {
            $end += strcspn($message, "\r\n", $end);
        }

        return [$matchStart, $end - $matchStart, $key . $separator . $quote . '[REDACTED]' . $quote];
    }

    /**
     * Whether the matched key belongs to the credential vocabulary (the
     * comparison is byte-wise case-insensitive, so the key's original case
     * survives into the replacement).
     */
    private static function isCredentialKey(string $key): bool
    {
        return array_any(
            self::CREDENTIAL_KEYS,
            static fn (string $candidate): bool => strcasecmp($candidate, $key) === 0,
        );
    }

    /**
     * Whether the candidate's first value token is an auth scheme keyword,
     * which marks the rest of the line as credential material.
     */
    private static function isAuthSchemeKeyword(string $valueToken): bool
    {
        return array_any(
            self::AUTH_SCHEME_KEYWORDS,
            static fn (string $keyword): bool => strcasecmp($keyword, $valueToken) === 0,
        );
    }
}
