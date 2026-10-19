<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * A listener for the resolving event (ROADMAP, "CONTAINER" area 9 -- the
 * reference container's `onResolving` hook, adapted to the declarative
 * grammar).
 *
 * Declared under the `listeners` > `resolving` configuration key: a
 * positional list of builder entries (`class` or `factory` plus
 * `arguments`), materialised once at boot and fired in declaration
 * order -- the firing order is the declaration order, the honest
 * spelling a priority key would only blur (a listener entry carrying
 * one is refused).
 *
 * The event fires when a construction actually starts: the cache-miss
 * path alone, before the guarded factory call runs -- a memoised answer
 * fires nothing. Every construction fires the event, the container's
 * own machinery identifiers included (the `@decor:` re-homed base and
 * its wrappers are constructions too); the *onion* alone reserves its
 * bypass, an observer sees what happens.
 *
 * A listener that throws propagates raw -- the resolution lane has
 * never wrapped a factory's own failure, and a listener's failure is
 * the same class of trust (the host's own machinery, the host's own
 * stack).
 */
interface ResolvingListener
{
    /**
     * The construction of the identifier is starting.
     */
    public function resolving(string $id): void;
}
