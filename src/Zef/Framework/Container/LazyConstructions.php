<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;
use ReflectionClass;

/**
 * The lazy lane's construction seam: the native lazy proxy PHP 8.4's
 * own reflection builds ({@see ReflectionClass::newLazyProxy()}),
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
 * The unit is also the composition root's programmatic spelling of
 * deferral: a host that wants one deferred service without the
 * declarative grammar embeds this call inside its own `set()`
 * closure, over the same seam the grammar's own lane rides.
 */
final readonly class LazyConstructions
{
    /**
     * Answers the native lazy proxy for the declared class.
     *
     * @param class-string      $class   the class the proxy stands in for
     * @param Closure(): object $factory the materialiser, run once at the
     *                                   first use of the proxy's product
     */
    public static function of(string $class, Closure $factory): object
    {
        return new ReflectionClass($class)->newLazyProxy($factory);
    }
}
