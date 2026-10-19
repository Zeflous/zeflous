<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * An interceptor around service construction (ROADMAP, "CONTAINER" area 9
 * -- the reference container's service-middleware / interceptor surface,
 * adapted to the declarative grammar).
 *
 * Declared under the `interceptors` configuration key: a positional list
 * of builder entries (`class` or `factory` plus `arguments`), each
 * materialised once at boot -- never a container service itself, because
 * resolving an interceptor through the container would run it through
 * the very seam it arms. The arguments follow the ordinary reference
 * grammar and resolve through the container at materialisation time.
 *
 * The interceptor wraps the *construction* step only: the cache-miss
 * path every lifetime lane rides (a memoised answer pays nothing). Its
 * seam order keeps the reference's own: the onion sits around the
 * guarded factory call, so an interceptor's frame is outside the
 * cycle-guard frame the construction runs in.
 *
 * `priority` orders the chain HIGHEST FIRST (the outermost), ties keep
 * declaration order -- the `middleware.stack` convention. The container's
 * own machinery identifiers (`@decor:`, `@contextual:`, `@value:`) bypass
 * the onion: an interceptor wraps the host's services, not the
 * container's plumbing, so a decorated identifier's chain is wrapped as
 * one construction rather than once per layer.
 */
interface ServiceInterceptor
{
    /**
     * One link in the construction onion.
     *
     * Call `$next()` to run the next layer inward (the innermost is the
     * guarded factory call itself); returning without calling it
     * short-circuits the construction -- the answer becomes the
     * service's instance, still passing through the resolved listeners
     * and the per-lifetime memoisation.
     *
     * @param Closure(): mixed $next the next layer inward
     *
     * @return mixed the instance this link answers for the identifier
     */
    public function intercept(string $id, Closure $next): mixed;
}
