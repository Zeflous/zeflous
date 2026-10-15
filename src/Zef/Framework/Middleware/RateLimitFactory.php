<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\ApcuCounterStore;
use Zef\Framework\Http\ApcuRateLimiter;
use Zef\Framework\Http\InMemoryRateLimiter;
use Zef\Framework\Http\PhpRedisCommands;
use Zef\Framework\Http\RateLimiterInterface;
use Zef\Framework\Http\RateLimitMiddleware;
use Zef\Framework\Http\RateLimitPolicy;
use Zef\Framework\Http\RateLimitStores;
use Zef\Framework\Http\RedisRateLimiter;
use Zef\Framework\Http\RedisSharedRateLimitStore;
use Zef\Framework\Http\SecurityFamilies;

/**
 * The rate-limit middleware's config-driven construction seam: the
 * factory the framework profile's own `security.rate_limit` service
 * registration names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * RateLimitFactory::class . '::fromConfig'` over the `framework.config`
 * service argument -- resolved through the container's existing
 * declarative grammar, exactly as the security-headers family's is. This
 * class is the grammar's string-addressed target, which is why it is
 * marked `@api` rather than being baselined or suppressed.
 *
 * Store selection is fail-closed, without a single environment-dependent
 * branch: `memory` needs nothing; `apcu` and `redis` are built directly,
 * and each fails its own honest way when the runner cannot honour the
 * declaration -- the redis boundary establishes its connection at
 * construction (boot time) and refuses fail-closed with the endpoint and
 * the remediation spelled out for every way the connection can fail
 * (the extension missing, the endpoint refusing, the connection
 * throwing), while the apcu boundary's absence surfaces through the
 * middleware's catch-all: an accounting call that cannot even be made
 * answers the fail-closed 503, never an unaccounted admission. The
 * reference fell back to the per-process limiter with an `error_log`
 * line instead -- a silent downgrade that multiplies the declared limit
 * by the worker count while the knob promises shared accounting, the
 * exact "protection the operator believes is running" failure the strict
 * reading exists to prevent.
 *
 * @api
 */
final readonly class RateLimitFactory
{
    /**
     * Builds the middleware from the frozen repository's `security
     * .rate_limit` subtree: the merged, already-defaulted knob map,
     * validated fail-closed by the policy, and the store the `store`
     * knob selected.
     */
    public static function fromConfig(Config $config): RateLimitMiddleware
    {
        $rateLimitPolicy = RateLimitPolicy::fromMap(
            SecurityFamilies::subtree($config->get('security'), 'rate_limit'),
        );

        return new RateLimitMiddleware($rateLimitPolicy, self::limiter($rateLimitPolicy));
    }

    /**
     * The store the policy's closed vocabulary selected: the
     * fall-through is the memory arm, which the validator's closed
     * vocabulary keeps exact.
     */
    private static function limiter(RateLimitPolicy $rateLimitPolicy): RateLimiterInterface
    {
        if ($rateLimitPolicy->store === RateLimitStores::APCU) {
            return new ApcuRateLimiter(new ApcuCounterStore());
        }

        if ($rateLimitPolicy->store === RateLimitStores::REDIS) {
            return new RedisRateLimiter(
                new RedisSharedRateLimitStore(
                    new PhpRedisCommands($rateLimitPolicy->redisHost, $rateLimitPolicy->redisPort),
                ),
            );
        }

        return new InMemoryRateLimiter($rateLimitPolicy->maxKeys);
    }
}
