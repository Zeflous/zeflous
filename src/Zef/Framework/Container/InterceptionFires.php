<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception lane's construction events (ROADMAP, "CONTAINER"
 * area 9 -- the reference container's resolving/resolved hooks' firing
 * half): the two materialised listener lists and the order they fire
 * in -- the resolving listeners when a construction starts, the
 * resolved listeners over its answer, a non-null answer replacing the
 * instance, null keeping it.
 */
final readonly class InterceptionFires
{
    /**
     * @param list<ResolvingListener> $resolving the materialised resolving listeners, in declaration order
     * @param list<ResolvedListener>  $resolved  the materialised resolved listeners, in declaration order
     */
    public function __construct(
        private array $resolving,
        private array $resolved,
    ) {
    }

    /**
     * The listeners' materialised firing lists: the two kinds' entries
     * built and proven ({@see InterceptionMaterialisations}), in
     * declaration order.
     */
    public static function over(ListenersMap $listenersMap, Container $container): self
    {
        return new self(
            InterceptionMaterialisations::resolving($listenersMap->resolving(), $container),
            InterceptionMaterialisations::resolved($listenersMap->resolved(), $container),
        );
    }

    /**
     * Whether the firing lists carry any listener.
     */
    public function carries(): bool
    {
        return $this->resolving !== [] || $this->resolved !== [];
    }

    /**
     * The resolving event: fired when a construction actually starts,
     * before the factory runs.
     */
    public function resolving(string $id): void
    {
        foreach ($this->resolving as $listener) {
            $listener->resolving($id);
        }
    }

    /**
     * The resolved event over the constructed instance: each listener
     * sees the previous listener's answer, so a replacement chain
     * composes in declaration order -- and a `null` answer keeps the
     * instance as constructed (the null the value shape legally
     * carries cannot mean "no instance" anywhere but here, at the seam
     * that owns one).
     *
     * @param mixed $instance the constructed instance
     */
    public function replaced(string $id, mixed $instance): mixed
    {
        return array_reduce(
            $this->resolved,
            static fn (mixed $carried, ResolvedListener $resolvedListener): mixed => $resolvedListener->resolved(
                $id,
                $carried,
            ) ?? $carried,
            $instance,
        );
    }
}
