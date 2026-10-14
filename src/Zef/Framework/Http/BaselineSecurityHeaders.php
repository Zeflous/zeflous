<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The security-headers family's baseline: the six hardening headers every
 * response carries, unconditionally, with their fixed values.
 *
 * These are not knobs. The family's posture (ported from the reference
 * implementation's milestone E2, legacy `zef-main`
 * `Middleware/SecurityHeadersMiddleware.php`) is a hardened baseline with
 * opt-in toggles on top: content-type sniffing is refused, framing is
 * denied, referrers are stripped, cross-domain policies are refused, and
 * the cross-origin opener and resource policies are pinned to same-origin.
 * An operator who wants one of them weakened removes the middleware from
 * the stack -- the configuration surface deliberately offers no spelling
 * that would silently weaken the baseline.
 *
 * Attribution: the six pairs are move-verbatim from the reference; the
 * always-on set is the reference's un-branched prefix of `withHeader()`
 * calls, lifted into the family's own pure-data unit so the pipeline's
 * stamping seam composes baseline and toggled pairs alike.
 *
 * @internal
 */
final readonly class BaselineSecurityHeaders
{
    /**
     * The six unconditional hardening pairs, in declaration order.
     *
     * @return list<array{non-empty-string, non-empty-string}>
     */
    public static function pairs(): array
    {
        return [
            ['X-Content-Type-Options', 'nosniff'],
            ['X-Frame-Options', 'DENY'],
            ['Referrer-Policy', 'no-referrer'],
            ['X-Permitted-Cross-Domain-Policies', 'none'],
            ['Cross-Origin-Opener-Policy', 'same-origin'],
            ['Cross-Origin-Resource-Policy', 'same-origin'],
        ];
    }
}
