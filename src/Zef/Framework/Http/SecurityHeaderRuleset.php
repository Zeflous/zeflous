<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The security-header audit's declared ruleset: the checks the
 * securityheaders.com scan grades, spelled as the framework's own
 * vocabulary -- the six unconditional hardening pairs the baseline
 * family stamps ({@see BaselineSecurityHeaders}), the two opt-in
 * toggles over them ({@see ToggledSecurityHeaders}: the
 * Content-Security-Policy and, over https only, the
 * Strict-Transport-Security), and the default-on Permissions-Policy,
 * each audited as a required check; plus the Cross-Origin-Embedder-
 * Policy the framework deliberately does not ship, audited as
 * advisory -- reported when present, never failing the audit, because
 * the family's posture is the reference's and COEP is not part of it.
 *
 * The alignment is deliberate and one-way: the ruleset mirrors what
 * the modern scan grades, and the audit answers against the response
 * an application actually produced, so a default posture (CSP off)
 * audits as an honest failure until the operator opts in -- the same
 * fail-visible posture every other gate in this repository keeps.
 *
 * @internal
 */
final readonly class SecurityHeaderRuleset
{
    /**
     * The declared checks, in audit order.
     *
     * @return list<SecurityHeaderCheck>
     */
    public static function checks(): array
    {
        return [
            new SecurityHeaderCheck(
                'X-Content-Type-Options',
                SecurityHeaderCheck::KIND_EXACT,
                ['nosniff'],
                true,
                false,
                'the response must refuse content-type sniffing outright',
            ),
            new SecurityHeaderCheck(
                'X-Frame-Options',
                SecurityHeaderCheck::KIND_ONE_OF,
                ['DENY', 'SAMEORIGIN'],
                true,
                false,
                'the response must refuse framing it does not consent to',
            ),
            new SecurityHeaderCheck(
                'Referrer-Policy',
                SecurityHeaderCheck::KIND_ONE_OF,
                [
                    'no-referrer',
                    'no-referrer-when-downgrade',
                    'origin',
                    'origin-when-cross-origin',
                    'same-origin',
                    'strict-origin',
                    'strict-origin-when-cross-origin',
                ],
                true,
                false,
                'the response must declare how much referrer it leaks',
            ),
            new SecurityHeaderCheck(
                'X-Permitted-Cross-Domain-Policies',
                SecurityHeaderCheck::KIND_EXACT,
                ['none'],
                true,
                false,
                'the response must refuse the cross-domain policy files',
            ),
            new SecurityHeaderCheck(
                'Cross-Origin-Opener-Policy',
                SecurityHeaderCheck::KIND_ONE_OF,
                ['same-origin', 'same-origin-allow-popups', 'noopener-allow-popups'],
                true,
                false,
                'the response must isolate its browsing context group',
            ),
            new SecurityHeaderCheck(
                'Cross-Origin-Resource-Policy',
                SecurityHeaderCheck::KIND_ONE_OF,
                ['same-origin', 'same-site', 'cross-origin'],
                true,
                false,
                'the response must declare who may embed it as a resource',
            ),
            new SecurityHeaderCheck(
                'Content-Security-Policy',
                SecurityHeaderCheck::KIND_NON_EMPTY,
                [],
                true,
                false,
                'the response must carry a content security policy',
            ),
            new SecurityHeaderCheck(
                'Strict-Transport-Security',
                SecurityHeaderCheck::KIND_MAX_AGE,
                [],
                true,
                true,
                'an https response must pin its transport for the max-age it declares',
            ),
            new SecurityHeaderCheck(
                'Permissions-Policy',
                SecurityHeaderCheck::KIND_NON_EMPTY,
                [],
                true,
                false,
                'the response must withdraw the permissions it does not use',
            ),
            new SecurityHeaderCheck(
                'Cross-Origin-Embedder-Policy',
                SecurityHeaderCheck::KIND_NON_EMPTY,
                [],
                false,
                false,
                'advisory: the framework ships no COEP posture; report it when the application adds one',
            ),
        ];
    }
}
