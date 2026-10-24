<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The session family's SameSite vocabulary: the closed set the
 * `http.session.same_site` knob may name, read case-insensitively into
 * the canonical spelling the `Set-Cookie` attribute carries.
 *
 * The vocabulary mirrors the CSRF family's own {@see CsrfSameSite}
 * (which itself mirrored {@see RateLimitStores} for the store
 * vocabulary): a knob that names a posture should not fail because
 * the environment's letter case differed -- `lax` reads as `Lax` --
 * and an unknown value is rejected with the value spelled out,
 * because `Laxy` is a typo of `Lax`, not a postured cookie.
 *
 * The two vocabulary folds stay separate on purpose, exactly as the
 * security map's census deferred its own seam until a second family
 * existed ({@see HttpFamilies}): the CSRF family's words and the
 * session family's words are two independent knob surfaces today,
 * and the shared cookie-attribute vocabulary is a fold a third
 * cookie-writing family's landing would justify -- not a seam this
 * slice may grow speculatively.
 */
final class SessionSameSite
{
    public const string STRICT = 'Strict';

    public const string LAX = 'Lax';

    public const string NONE = 'None';

    /**
     * The vocabulary's canonical forms, keyed by their folded spelling.
     */
    private const array FOLDED = [
        'strict' => self::STRICT,
        'lax' => self::LAX,
        'none' => self::NONE,
    ];

    /**
     * Reads one SameSite knob's value through the closed vocabulary.
     *
     * @throws HttpException when the value is not one of the known words
     */
    public static function read(mixed $value): string
    {
        if (\is_string($value)) {
            return self::FOLDED[mb_strtolower($value)] ?? self::rejected($value);
        }

        return self::rejected($value);
    }

    /**
     * The vocabulary rejection: the value spelled out, the known words
     * named.
     */
    private static function rejected(mixed $value): string
    {
        throw new HttpException(
            'The http.session same_site knob must be one of the SameSite vocabulary '
            . '(Strict, Lax, None), ' . (\is_string($value) ? "'" . $value . "'" : get_debug_type($value)) . ' given.',
        );
    }
}
