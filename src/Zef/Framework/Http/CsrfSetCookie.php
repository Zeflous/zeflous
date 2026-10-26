<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CSRF family's issuance line: the fresh token as the family's own
 * `Set-Cookie` header value, attributes policy-controlled throughout.
 *
 * The value is always the freshly issued token -- no request input is
 * embedded -- and the attribute order is the reference implementation's
 * own ({@see CsrfMiddleware} carries the port's provenance): `Path=/`,
 * `SameSite` from the vocabulary, `Secure` over https when the knob
 * asks (a local http bootstrap still receives its token), and
 * `HttpOnly` when the knob asks. The one exception the browser
 * contract forces: `SameSite` `None` carries `Secure` whatever the
 * scheme -- no current browser accepts a `None` cookie without it, so
 * emitting one and hoping is issuing a cookie that will be dropped.
 * The policy's SameSite `None` word already required the Secure knob
 * at validation ({@see CsrfPolicy}), so no posture can reach this line
 * with an unsatisfiable request.
 */
final class CsrfSetCookie
{
    /**
     * Builds the fresh token's Set-Cookie header value.
     */
    public static function headerValue(CsrfPolicy $csrfPolicy, string $token, string $scheme): string
    {
        $attributes = [
            $csrfPolicy->cookieName . '=' . $token,
            'Path=/',
            'SameSite=' . $csrfPolicy->sameSite,
        ];

        if ($csrfPolicy->cookieSecure && ($scheme === 'https' || $csrfPolicy->sameSite === SameSiteVocabulary::NONE)) {
            $attributes[] = 'Secure';
        }

        if ($csrfPolicy->cookieHttpOnly) {
            $attributes[] = 'HttpOnly';
        }

        return implode('; ', $attributes);
    }
}
