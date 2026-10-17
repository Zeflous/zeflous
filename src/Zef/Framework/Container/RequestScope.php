<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * One request scope: the per-request resolution surface a request-scoped
 * service is legal in (ROADMAP, "CONTAINER" area 5 -- the persistent-
 * runtime boundary a RoadRunner/Swoole/FrankenPHP worker opens per
 * request and closes when it answers).
 *
 * The scope is a facade, not a second container: definitions and their
 * guarded factories stay the {@see ServiceStore}'s, and the memoisation
 * of request-scoped ids inside this scope stays here -- the scope owns
 * its own instances ({@see RequestInstances} drives the lane through
 * the answer seam below, so a factory resolving a fellow request-scoped
 * dependency mid-resolution lands it in the same scope). Closing
 * releases the memoised instances without touching any singleton the
 * scope's resolutions may have built on the way.
 *
 * A closed scope refuses every further resolution: the request it named
 * is over, and an instance built after it would belong to no request.
 */
final class RequestScope
{
    /** @var array<string, mixed> */
    private array $instances = [];

    private bool $closed = false;

    public function __construct(
        private readonly RequestInstances $requestInstances,
        private readonly Container $container,
    ) {
    }

    /**
     * Resolves the identifier inside this scope: request-scoped ids
     * memoise here, wider lifetimes resolve exactly as they do at the
     * container's root.
     */
    public function get(string $id): mixed
    {
        if ($this->closed) {
            throw RequestScopeRefusals::closed($id);
        }

        return $this->requestInstances->resolveScoped($id, $this->container, $this);
    }

    /**
     * Whether the underlying container carries the identifier: the
     * definition-level question, exactly the root container's own. A
     * closed scope carries nothing -- the request it named is over.
     */
    public function has(string $id): bool
    {
        if ($this->closed) {
            return false;
        }

        return $this->container->has($id);
    }

    /**
     * Closes the scope: memoised request-scoped instances are released,
     * further resolutions through it are refused.
     */
    public function close(): void
    {
        $this->closed = true;
        $this->instances = [];
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * The scope machinery's seam ({@see RequestInstances}): the memo
     * hit answers directly, the miss runs the build and records its
     * answer -- refused honestly when the scope closed while the build
     * itself ran (a factory closing the very scope that is building
     * it: the completed instance has no scope left to belong to).
     *
     * @param Closure(): mixed $build the guarded factory call
     */
    public function answer(string $id, Closure $build): mixed
    {
        if (\array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $instance = $build();
        if ($this->closed) {
            throw RequestScopeRefusals::closedMidResolution($id);
        }

        $this->instances[$id] = $instance;

        return $instance;
    }
}
