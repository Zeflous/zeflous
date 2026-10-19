<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The warmup orchestration the scope & lifecycle area deferred to the
 * compiler-pass lane (ROADMAP area 5's KDD, area 8's knob): the eager
 * construction of every singleton-lifetime definition, once, right
 * after the install loop -- so the first request never pays the
 * construction costs and a definition that cannot construct at all
 * halts the boot it belongs to instead of the first request it would
 * have failed.
 *
 * The scope is exactly the singleton lane: a request-lifetime
 * definition belongs to a scope the boot does not own, and a transient
 * one is *defined* by its fresh-per-resolution answer -- warming either
 * would be a contradiction, not an optimisation. The lane is opt-in
 * (`aot.warm`) and runs identically over both boots, the full compile
 * and the artifact load, because what it warms is the container's
 * memoisation, which both lanes install through the same
 * {@see Container::set()}.
 *
 * A definition whose construction throws halts the boot with the
 * construction failure's own message: the warmer adds no wrapping,
 * because the failure the host must see is the one the constructor
 * already raised.
 */
final readonly class SingletonWarmer
{
    /**
     * Eagerly constructs each named singleton through the container's
     * own resolution lane.
     *
     * @param list<string> $singletonIds the singleton-lifetime identifiers,
     *                                   in declaration order
     */
    public static function warm(array $singletonIds, Container $container): void
    {
        foreach ($singletonIds as $singletonId) {
            $container->get($singletonId);
        }
    }
}
