<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CSRF family's cookie reader: one cookie value out of a raw
 * `Cookie` header, under the reference implementation's own pair
 * grammar.
 *
 * The grammar is move-verbatim from the reference (legacy `zef-main`
 * `Framework/Security/SecurityRuntimeMiddleware.php`, the CSRF arm of
 * milestone E2's runtime boundary): pairs split on `;` and trimmed,
 * each pair split on the first `=` only (values may themselves carry
 * `=`), the key compared byte-for-byte -- never loosely, so a numeric
 * lookalike name (`0x1F`) never equates the family's own (`31`) the
 * way PHP's `==` would. Absent answers null; an empty value answers
 * the empty string -- distinct from absent, and refused on the unsafe
 * path like any value the secret did not sign.
 */
final class CsrfCookieValue
{
    /**
     * Reads one cookie value out of the raw header, null when the
     * cookie is absent.
     */
    public static function from(string $header, string $name): ?string
    {
        foreach (explode(';', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', mb_trim($pair), 2), 2, '');

            if ($key === $name) {
                return mb_trim($value);
            }
        }

        return null;
    }
}
