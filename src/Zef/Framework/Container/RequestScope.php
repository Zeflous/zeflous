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
     *
     * The caller this read waits for is the HTTP roadmap's §5.1
     * runtime adapter (phase 2, the persistent-worker lane), which
     * holds the scope a request long -- the same surface
     * {@see Container::createRequestScope()} opens. The psalm
     * baseline carries the deferred consumer.
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
     *
     * Part of the scope's own lifecycle surface the §5.1 runtime
     * adapter consumes (phase 2); the psalm baseline carries the
     * deferred consumer.
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
     *
     * The request-end half of the §5.1 runtime adapter's lifecycle
     * (phase 2, the persistent-worker lane): open per request, closed
     * at its end. The psalm baseline carries the deferred consumer.
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

        return $this->record($id, $build());
    }

    /**
     * The build's answer, captured once as this seam's own parameter
     * -- the one value the judgements below read, because the answer
     * must live somewhere while the mid-resolution check runs between
     * the guarded factory call and the memo write it guards: the
     * store is the commitment, and the assignment the return spells
     * answers it.
     *
     * @param mixed $instance the guarded factory call's answer -- the
     *                        service's own type, whatever it is
     *
     * @return mixed the memoised instance
     */
    private function record(string $id, mixed $instance): mixed
    {
        if ($this->closed) {
            throw RequestScopeRefusals::closedMidResolution($id);
        }

        return $this->instances[$id] = $instance;
    }
}
