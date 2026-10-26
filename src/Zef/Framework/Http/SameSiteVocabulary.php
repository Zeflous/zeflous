<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The cookie families' shared SameSite vocabulary: the closed set the
 * `same_site` knobs may name, read case-insensitively into the canonical
 * spelling the `Set-Cookie` attribute carries.
 *
 * The reading folds the environment's raw spelling through the Unicode
 * case map and answers the canonical form (`strict` reads as `Strict`),
 * mirroring {@see RateLimitStores} for the store vocabulary: a knob that
 * names a posture should not fail because the environment's letter case
 * differed. An unknown value is rejected with the value spelled out --
 * `Strictly` is a typo of `Strict`, not a postured cookie -- and the
 * rejection names the knob surface that was asked, so the CSRF family's
 * `security.csrf` and the session family's `http.session` keep their own
 * diagnosis while sharing one reading.
 *
 * The two vocabulary folds were separate classes ({@see CsrfPolicy} and
 * {@see SessionPolicy} carried their own) until the copy-paste detector
 * (`composer qa:duplication`, the local duplication gate) found the pair
 * of identical 38-line bodies and made the shared fold the least-code
 * spelling: the census doctrine had deferred this seam until a third
 * cookie-writing family landed, and the duplication gate's own arrival
 * answered that deferral -- two byte-identical bodies are a clone
 * regardless of how many knob surfaces ask the question.
 */
final class SameSiteVocabulary
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
     * Reads one `same_site` knob's value through the closed vocabulary.
     *
     * @param string $knob the knob surface the value came from, spelled
     *                     into the rejection verbatim (e.g. `security.csrf`)
     *
     * @throws HttpException when the value is not one of the known words
     */
    public static function read(mixed $value, string $knob): string
    {
        if (\is_string($value)) {
            return self::FOLDED[mb_strtolower($value)] ?? self::rejected($value, $knob);
        }

        return self::rejected($value, $knob);
    }

    /**
     * The vocabulary rejection: the value spelled out, the known words
     * named, the knob surface that was asked.
     */
    private static function rejected(mixed $value, string $knob): string
    {
        throw new HttpException(
            'The ' . $knob . ' same_site knob must be one of the SameSite vocabulary '
            . '(Strict, Lax, None), ' . (\is_string($value) ? "'" . $value . "'" : get_debug_type($value)) . ' given.',
        );
    }
}
