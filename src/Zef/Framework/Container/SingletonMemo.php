<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The singleton memo behind {@see ServiceStore}: the instances a
 * singleton-lifetime resolution answered, keyed by identifier, read
 * back by the resolution lane and forgotten by a re-registration.
 *
 * Split from the store as its own unit so the memo's vocabulary (the
 * answer-or-build seam plus the forget of a re-registration) stays
 * archeology-sized -- the store keeps the lanes that consult it.
 */
final class SingletonMemo
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->resolved);
    }

    /**
     * The memoised instance itself (the has() answer said it exists) --
     * the resolution lane's fast path.
     */
    public function instance(string $id): mixed
    {
        return $this->resolved[$id];
    }

    /**
     * The singleton lane: the memo hit answers directly, the miss runs
     * the build and records its answer -- the exact seam the
     * request lane spells over its own scopes ({@see RequestScope}).
     *
     * @param Closure(): mixed $build the guarded factory call
     */
    public function answer(string $id, Closure $build): mixed
    {
        if (\array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        $this->resolved[$id] = $build();

        return $this->resolved[$id];
    }

    /**
     * A re-registration's invalidation: the previous instance must
     * never answer again.
     */
    public function forget(string $id): void
    {
        unset($this->resolved[$id]);
    }
}
