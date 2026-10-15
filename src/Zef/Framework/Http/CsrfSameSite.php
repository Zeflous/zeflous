<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CSRF family's SameSite vocabulary: the closed set the `security
 * .csrf.same_site` knob may name, read case-insensitively into the
 * canonical spelling the `Set-Cookie` attribute carries.
 *
 * The reading folds the environment's raw spelling through the Unicode
 * case map and answers the canonical form (`strict` reads as `Strict`),
 * mirroring {@see RateLimitStores} for the store vocabulary: a knob that
 * names a posture should not fail because the environment's letter case
 * differed. An unknown value is rejected with the value spelled out --
 * `Strictly` is a typo of `Strict`, not a postured cookie.
 */
final class CsrfSameSite
{
    public const string STRICT = 'Strict';

    public const string LAX = 'Lax';

    public const string NONE = 'None';

    /**
     * The vocabulary's canonical forms, keyed by their folded spelling.
     *
     * @var array<string, 'Strict'|'Lax'|'None'>
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
            'The security.csrf same_site knob must be one of the SameSite vocabulary '
            . '(Strict, Lax, None), ' . (\is_string($value) ? "'" . $value . "'" : get_debug_type($value)) . ' given.',
        );
    }
}
