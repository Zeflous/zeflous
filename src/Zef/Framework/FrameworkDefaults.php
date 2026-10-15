<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Middleware\CsrfFactory;
use Zef\Framework\Middleware\RateLimitFactory;
use Zef\Framework\Middleware\SecurityHeadersFactory;

/**
 * The framework's own configuration defaults: the code-declared profile the
 * {@see ApplicationBootstrapper} lays down as the Default layer under the
 * application's `app/configs/` files and the environment (see `ROADMAP.md`,
 * "Configuration System + DSL + Radix Tree" -- Default < App < Env).
 *
 * Defaults live in code, not in a shipped file, so they can never be
 * accidentally deleted or half-edited by a host application: a host that
 * never writes a configuration file boots on exactly this profile, and a
 * host that writes one overrides only the keys it cares about -- the
 * Laravel-style vendor-defaults seam, adapted to the layered builder the
 * configuration system already owns.
 *
 * The profile now ships the security-headers, rate-limit and CSRF
 * families, and it ships them the way every family will arrive: the
 * subsystem's *service registration* as a declarative entry under the reserved
 * `framework.services` key the container's existing grammar installs
 * (string-addressed, over the `framework.config` service the bootstrapper
 * registers), and the subsystem's *knobs* under the family's own key. The `framework`
 * namespace is the machinery's own -- a host's `services` key is the
 * host's declaration surface and stays fully theirs, which is also why
 * the registration does not live there: the merge contract counts an
 * empty array as a clearing list, so a host's empty `services.php` would
 * wipe the framework's own registrations out of the merged tree. The
 * registration always exists -- a host cannot delete the framework's own
 * machinery out from under it -- while stack membership stays the host's
 * explicit choice: the `middleware.stack` list decides what runs, exactly
 * as it does for any other middleware. The remaining growth follows the
 * roadmap's order -- the authentication family lands here with its PR, the
 * HTTP lane's ingress knobs (`http.body_budget`, `http.trusted_hosts`, ...)
 * migrate with their wiring, the container's
 * `aot` switch arrives with the compile/dump area (roadmap area 8), and
 * `tags` with the autoconfiguration area (roadmap area 3).
 */
final readonly class FrameworkDefaults
{
    /**
     * The container service identifier under which the frozen repository is
     * registered at boot: the profile's own registrations address it (their
     * factories resolve the repository through it), and the bootstrapper
     * installs it before a single definition. The identifier lives here --
     * with the declarations that reference it -- so the kernel's own
     * dependency graph stays one-directional.
     */
    public const string CONFIG_SERVICE = 'framework.config';

    /**
     * The framework's default configuration profile.
     *
     * @return array<non-empty-string, mixed>
     */
    public static function profile(): array
    {
        return [
            'framework' => [
                'services' => [
                    'security.headers' => [
                        'factory' => SecurityHeadersFactory::class . '::fromConfig',
                        'arguments' => [
                            ['service' => self::CONFIG_SERVICE],
                        ],
                    ],
                    'security.rate_limit' => [
                        'factory' => RateLimitFactory::class . '::fromConfig',
                        'arguments' => [
                            ['service' => self::CONFIG_SERVICE],
                        ],
                    ],
                    'security.csrf' => [
                        'factory' => CsrfFactory::class . '::fromConfig',
                        'arguments' => [
                            ['service' => self::CONFIG_SERVICE],
                        ],
                    ],
                ],
            ],
            'security' => [
                'headers' => [
                    'hsts' => false,
                    'csp' => false,
                    'content_security_policy' => "default-src 'self'; frame-ancestors 'none'; base-uri 'self'",
                    'permissions_policy' => true,
                ],
                'rate_limit' => [
                    'enabled' => false,
                    'limit' => 100,
                    'window' => 60,
                    'max_keys' => 10000,
                    'store' => 'memory',
                    'trusted_proxies' => [],
                    'redis_host' => '127.0.0.1',
                    'redis_port' => 6379,
                ],
                'csrf' => [
                    'enabled' => true,
                    'secret' => '',
                    'cookie_name' => 'ZEF-XSRF-TOKEN',
                    'header_name' => 'X-CSRF-Token',
                    'token_bytes' => 32,
                    'cookie_secure' => true,
                    'cookie_http_only' => true,
                    'same_site' => 'Strict',
                ],
            ],
        ];
    }
}
