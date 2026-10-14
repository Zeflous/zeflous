<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The security-headers family's toggled half: the pairs whose presence a
 * policy knob decides.
 *
 * Three headers live here, each with its own applicability rule:
 *
 * - `Permissions-Policy` -- the reference's fixed deny-all camera,
 *   microphone, geolocation and payment grants, when the knob is on (the
 *   family's only default-on toggle);
 * - `Content-Security-Policy` -- the policy string the knob selected,
 *   defaulting to the family's standard `default-src 'self'` policy;
 * - `Strict-Transport-Security` -- the reference's fixed one-year
 *   `max-age` with `includeSubDomains`, gated twice: the knob must be on,
 *   and the request must have arrived over `https` (an HSTS header on a
 *   cleartext response is noise a browser never persists -- the reference
 *   refuses to emit it there, and so does this port).
 *
 * The HSTS `max-age` and the Permissions-Policy value are deliberately
 * fixed rather than knob-driven: the family's configuration surface keeps
 * exactly the toggles the reference exposed, and a host needing a
 * different max-age composes its own middleware over the same response.
 *
 * @internal
 */
final readonly class ToggledSecurityHeaders
{
    /**
     * The conditional hardening pairs, in declaration order.
     *
     * @return list<array{non-empty-string, string}>
     */
    public static function pairs(SecurityHeadersPolicy $securityHeadersPolicy, string $scheme): array
    {
        $pairs = [];

        if ($securityHeadersPolicy->permissionsPolicy) {
            $pairs[] = ['Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()'];
        }

        if ($securityHeadersPolicy->contentSecurityPolicyEnabled) {
            $pairs[] = ['Content-Security-Policy', $securityHeadersPolicy->contentSecurityPolicy];
        }

        if ($securityHeadersPolicy->hsts && $scheme === 'https') {
            $pairs[] = ['Strict-Transport-Security', 'max-age=31536000; includeSubDomains'];
        }

        return $pairs;
    }
}
