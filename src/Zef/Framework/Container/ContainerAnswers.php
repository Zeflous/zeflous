<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The container's answer lane: the whole dispatch behind the PSR
 * facade's get() and has() -- the registered lane when the store
 * carries the identifier, the covering fallback's lane when it does
 * not. Split from {@see ServiceStore} as its own unit so the store
 * keeps the registration surface and the collaborators' wiring, and
 * the miss-path dispatch stays the resolution concern it is
 * ({@see ServiceResolutions} posture: a lane, its own unit).
 *
 * has() answers the PSR-11 letter exactly: whether get() will refuse
 * the identifier -- the store's registrations or a covering fallback.
 */
final readonly class ContainerAnswers
{
    public function __construct(
        private ServiceStore $serviceStore,
        private NamespaceFallbacks $namespaceFallbacks,
    ) {
    }

    /**
     * The whole answer for an identifier: the registered lane or the
     * covering fallback's.
     */
    public function answer(string $id, Container $container): mixed
    {
        if ($this->serviceStore->has($id)) {
            return $this->serviceStore->resolve($id, $container);
        }

        return $this->namespaceFallbacks->resolve($id, $container);
    }

    /**
     * Whether any lane answers the identifier.
     */
    public function carries(string $id): bool
    {
        return $this->serviceStore->has($id) || $this->namespaceFallbacks->covers($id);
    }
}
