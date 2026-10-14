<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Redis;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\ApcuCounterStore;
use Zef\Framework\Http\ApcuRateLimiter;
use Zef\Framework\Http\HttpException;
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
 * Store selection is fail-closed, at boot: `memory` needs nothing;
 * `apcu` demands the APCu extension and `redis` demands the phpredis
 * extension and a reachable endpoint, each halting the boot with the
 * remediation spelled out when absent. The reference fell back to the
 * per-process limiter with an `error_log` line instead -- a silent
 * downgrade that multiplies the declared limit by the worker count while
 * the knob promises shared accounting, the exact "protection the
 * operator believes is running" failure the strict reading exists to
 * prevent. Declaring a store is a statement of the accounting topology
 * the operator wants; the boot refuses to promise what it cannot keep.
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
     * The store the policy's closed vocabulary selected: the extension
     * boundaries are checked here, once, at boot -- the fall-through is
     * the memory arm, which the validator's closed vocabulary keeps
     * exact.
     */
    private static function limiter(RateLimitPolicy $rateLimitPolicy): RateLimiterInterface
    {
        if ($rateLimitPolicy->store === RateLimitStores::APCU) {
            if (!\function_exists('apcu_inc')) {
                throw new HttpException(
                    'The security.rate_limit store apcu requires the APCu extension'
                    . ' (install ext-apcu, or declare the memory store).',
                );
            }

            return new ApcuRateLimiter(new ApcuCounterStore());
        }

        if ($rateLimitPolicy->store === RateLimitStores::REDIS) {
            if (!class_exists(Redis::class)) {
                throw new HttpException(
                    'The security.rate_limit store redis requires the phpredis extension'
                    . ' (install ext-redis, or declare the memory store).',
                );
            }

            return new RedisRateLimiter(
                new RedisSharedRateLimitStore(
                    new PhpRedisCommands($rateLimitPolicy->redisHost, $rateLimitPolicy->redisPort),
                ),
            );
        }

        return new InMemoryRateLimiter($rateLimitPolicy->maxKeys);
    }
}
