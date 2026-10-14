<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\SecurityHeadersMiddleware;
use Zef\Framework\Http\SecurityHeadersPolicy;

/**
 * The security-headers middleware's config-driven construction seam: the
 * factory the framework profile's own `security.headers` service
 * registration names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * SecurityHeadersFactory::class . '::fromConfig'` over the `framework.config`
 * service argument -- so the container resolves the middleware through its
 * existing declarative grammar: no new container surface, no
 * Kernel-to-HTTP widening, and the frozen configuration repository itself
 * is the one dependency. This class is the grammar's string-addressed
 * target, which is why it is marked `@api` rather than being baselined or
 * suppressed: it is consumed through the configuration the boot installs,
 * not through a statically visible call.
 *
 * @api
 */
final readonly class SecurityHeadersFactory
{
    /**
     * Builds the middleware from the frozen repository's `security.headers`
     * subtree: the merged, already-defaulted knob map the layered builder
     * produced (Default < App < Env), validated fail-closed by the policy.
     */
    public static function fromConfig(Config $config): SecurityHeadersMiddleware
    {
        return new SecurityHeadersMiddleware(SecurityHeadersPolicy::fromMap($config->get('security.headers')));
    }
}
