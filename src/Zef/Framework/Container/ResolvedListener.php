<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * A listener for the resolved event (ROADMAP, "CONTAINER" area 9 -- the
 * reference container's `onResolved` hook, adapted to the declarative
 * grammar).
 *
 * Declared under the `listeners` > `resolved` configuration key: a
 * positional list of builder entries (`class` or `factory` plus
 * `arguments`), materialised once at boot and fired in declaration
 * order -- each listener sees the previous listener's answer, so a
 * replacement chain composes in declaration order.
 *
 * The event fires when a construction has produced its instance --
 * inside the per-lifetime memoisation's build step, so a non-null
 * answer replaces the instance *before* the memo records it: the
 * singleton lane's answer, the request scope's answer and the
 * transient's every answer all fire it, and a memoised answer fires
 * nothing.
 *
 * A `null` answer keeps the instance as constructed -- the reference's
 * own spelling, kept whole: null is a legal service value under
 * zeflous's value shape, so it cannot carry the "no replacement"
 * meaning anywhere but here, at the seam that owns one.
 *
 * A listener that throws propagates raw (the {@see ResolvingListener}
 * discipline): the memoisation stays empty when the build throws, so a
 * later resolution honestly re-runs the construction and the listeners.
 */
interface ResolvedListener
{
    /**
     * The construction of the identifier has produced its instance.
     *
     * @return mixed the instance the memoisation should record -- a
     *               non-null answer replaces the constructed one, null
     *               keeps it
     */
    public function resolved(string $id, mixed $instance): mixed;
}
