<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The lazy lane's construction seam: the native lazy proxy PHP 8.4's
 * own reflection builds ({@see \ReflectionClass::newLazyProxy()}),
 * the zero-dependency answer to ROADMAP area 7's "proxy lazy".
 *
 * The proxy is an instance of the declared class itself -- not a
 * generated subclass, which is why `final` and `readonly` classes
 * defer as honestly as plain ones do -- created without its
 * constructor, standing in for the real instance from the moment the
 * resolution lane answers it. The first use (any method call,
 * property read, or any other member access) runs the given factory
 * once, behind the {@see CycleGuard} and the interception onion the
 * materialiser closure embeds ({@see ServiceResolutions}), and every
 * further use forwards to the real instance PHP kept.
 *
 * The factory's answer must be an instance of the declared class:
 * PHP itself refuses, at materialisation, an answer that is not --
 * the runtime guard a lying factory declaration cannot slip past.
 *
 * The reflection the proxy construction rides is the caller's own
 * memo ({@see ReflectionMemo}, ROADMAP area 11's reflection caching):
 * the resolution lane hands its per-container memo so a transient's
 * every resolution reuses the one carried reflection instead of
 * constructing a fresh one, while a host embedding the programmatic
 * spelling inside their own `set()` closure -- the composition root's
 * deferred-service idiom this unit also serves -- passes their own
 * memo (or a fresh one, when a single proxy is all they build).
 */
final readonly class LazyConstructions
{
    /**
     * Answers the native lazy proxy for the declared class.
     *
     * @param ReflectionMemo    $reflectionMemo the memo the proxy
     *                                          construction's reflection rides
     * @param class-string      $class          the class the proxy stands in for
     * @param Closure(): object $factory        the materialiser, run once at the
     *                                          first use of the proxy's product
     */
    public static function of(ReflectionMemo $reflectionMemo, string $class, Closure $factory): object
    {
        return $reflectionMemo->reflect($class)->newLazyProxy($factory);
    }
}
