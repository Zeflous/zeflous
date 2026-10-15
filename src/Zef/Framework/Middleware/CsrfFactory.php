<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\CsrfBounds;
use Zef\Framework\Http\CsrfMiddleware;
use Zef\Framework\Http\CsrfPolicy;
use Zef\Framework\Http\CsrfTokenManager;
use Zef\Framework\Http\SecurityFamilies;

/**
 * The CSRF middleware's config-driven construction seam: the factory
 * the framework profile's own `security.csrf` service registration
 * names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * CsrfFactory::class . '::fromConfig'` over the `framework.config`
 * service argument -- resolved through the container's existing
 * declarative grammar, exactly as the security-headers and rate-limit
 * families' are. This class is the grammar's string-addressed target,
 * which is why it is marked `@api` rather than being baselined or
 * suppressed.
 *
 * Validation is the family's own ({@see CsrfPolicy::fromMap()}), and it
 * is fail-loud where the reference fell silent: an armed family with no
 * usable signing secret halts the resolution with the remediation
 * spelled out ({@see CsrfBounds}) -- the reference
 * answered its default-on-without-secret posture by disabling the
 * family behind an `error_log` line, a silent downgrade of exactly the
 * kind the rate-limit store selection already refuses (KDD-RL1 in
 * `docs/ci-notes/security-runtime.md`): a protection the operator
 * believes is running while it is not. Here the stack membership is the
 * explicit opt-in, so the absent secret is the explicit opt-in the
 * framework cannot honour -- and the boot says so instead of quietly
 * dropping the boundary.
 *
 * @api
 */
final readonly class CsrfFactory
{
    /**
     * Builds the middleware from the frozen repository's `security
     * .csrf` subtree: the merged, already-defaulted knob map, validated
     * fail-closed by the policy, over the token manager the secret and
     * the entropy knob configure -- an armed policy only: the disarmed
     * one carries no usable secret by construction, and a boundary that
     * will never consult a manager carries none.
     *
     * This resolution is the family's fail-loud seam: the armed branch
     * asserts the signing secret is one it can sign with ({@see
     * CsrfBounds::assertUsableSecret()}) before any manager is built,
     * because being resolved here means the stack membership opted in.
     */
    public static function fromConfig(Config $config): CsrfMiddleware
    {
        $csrfPolicy = CsrfPolicy::fromMap(
            SecurityFamilies::subtree($config->get('security'), 'csrf'),
        );

        if (!$csrfPolicy->enabled) {
            return new CsrfMiddleware($csrfPolicy, null);
        }

        CsrfBounds::assertUsableSecret($csrfPolicy->secret);

        return new CsrfMiddleware(
            $csrfPolicy,
            new CsrfTokenManager($csrfPolicy->secret, $csrfPolicy->tokenBytes),
        );
    }
}
