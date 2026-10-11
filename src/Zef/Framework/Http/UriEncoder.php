<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The URI percent-encoder: one component at a time, each under its own
 * allowed charset, existing triples uppercased and preserved.
 *
 * Pure static helper, extracted so the parser and the withers share one
 * encoder while the PSR-7 surface stays under the architecture gate's
 * difficulty budget. Iteration is byte-wise through `isset()` offsets --
 * deliberately not `strlen()` arithmetic, which the coding standard's
 * multibyte-safe fixer would silently turn into character arithmetic.
 * A percent triple is recognized through two look-ahead bytes read with a
 * `?? ''` default: `ctype_xdigit('')` is false, so the guard doubles as
 * the end-of-string check, and folding the pair through `strtr` keeps the
 * hex alphabet (inherently ASCII) away from the multibyte case functions.
 */
final class UriEncoder
{
    /**
     * The path component's allowed characters (RFC 3986 pchar minus the
     * percent, which the encoder itself owns).
     */
    public const string PATH_ALLOWED = ":/@!$&'()*+,;=-._~";

    /**
     * The query and fragment components' allowed characters (pchar plus
     * the "/" and "?" separators).
     */
    public const string QUERY_FRAGMENT_ALLOWED = ":/?@!$&'()*+,;=-._~";

    /**
     * The userinfo user component's allowed characters.
     */
    public const string USERINFO_ALLOWED = "!$&'()*+,;=:";

    /**
     * RFC 3986 userinfo sub-delims allowed in the pass component (a
     * charset, not a credential).
     */
    public const string USERINFO_PASS_ALLOWED = "!$&'()*+,;=";

    private const string UNRESERVED_PREFIX = 'A-Za-z0-9\-._~';

    private const string HEX_LOWER = 'abcdef';

    private const string HEX_UPPER = 'ABCDEF';

    /**
     * Percent-encode one URI component, preserving allowed characters.
     */
    public static function encodeComponent(string $value, string $allowed): string
    {
        $preserve = \sprintf('/^[%s%s]\z/', self::UNRESERVED_PREFIX, preg_quote($allowed, '/'));
        $result = '';
        $i = 0;
        while (isset($value[$i])) {
            $first = $value[$i + 1] ?? '';
            $second = $value[$i + 2] ?? '';
            if ($value[$i] === '%' && ctype_xdigit($first) && ctype_xdigit($second)) {
                $result .= '%' . strtr($first . $second, self::HEX_LOWER, self::HEX_UPPER);
                $i += 3;

                continue;
            }

            if (preg_match($preserve, $value[$i]) === 1) {
                $result .= $value[$i];
                ++$i;

                continue;
            }

            $result .= \sprintf('%%%02X', \ord($value[$i]));
            ++$i;
        }

        return $result;
    }

    /**
     * Percent-encodes the user (and, when present, the password) into the
     * userinfo component, each half under its own allowed charset.
     */
    public static function encodeUserInfo(string $user, ?string $pass): string
    {
        $userInfo = self::encodeComponent($user, self::USERINFO_ALLOWED);
        if ($pass !== null) {
            $userInfo .= ':' . self::encodeComponent($pass, self::USERINFO_PASS_ALLOWED);
        }

        return $userInfo;
    }
}
