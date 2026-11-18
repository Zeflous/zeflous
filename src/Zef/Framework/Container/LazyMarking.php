<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The lazy lane's marking step: the guarded single mark the
 * composition root's programmatic surface takes
 * ({@see Container::markLazy()}), and the boot's own post-install
 * walk over the planned definition set ({@see LazyMarking::marks()}),
 * one spelling for both.
 *
 * The guards are the identifiers that cannot defer: an identifier
 * with no registration to defer behind, a request-lifetime
 * registration whose materialisation could not name its scope
 * ({@see LazyRefusals}), and -- the audit's silent no-op (#783) --
 * an identifier whose answer the memo already carries, whose mark
 * the resolution lanes would answer before consulting (the registry
 * consulted behind the same class is the idempotent re-mark, the
 * boot's own repeat spelling: the memo holds the proxy, the
 * deferral already in effect). The registry itself keeps the marks
 * ({@see LazyIdentities}); a `set()` re-registration forgets them
 * with the memo ({@see ServiceStore::set()}).
 */
final readonly class LazyMarking
{
    /**
     * Marks one registered identifier lazy behind the class, refusing
     * the identifiers that cannot defer.
     *
     * @param class-string $class the class the proxy stands in for
     */
    public static function mark(
        FactoryRegistrations $factoryRegistrations,
        LazyIdentities $lazyIdentities,
        string $id,
        string $class,
        bool $answerAlreadyMemoised,
    ): void {
        if (!$factoryRegistrations->has($id)) {
            throw LazyRefusals::unknownService($id, $class);
        }

        if ($factoryRegistrations->lifetime($id) === ServiceLifetime::REQUEST) {
            throw LazyRefusals::lifetimeCannotDefer($id);
        }

        if ($answerAlreadyMemoised && $lazyIdentities->classOf($id) === null) {
            throw LazyRefusals::alreadyAnswered($id, $class);
        }

        $lazyIdentities->mark($id, $class);
    }

    /**
     * The boot's own step: every planned definition that asks to
     * defer, marked behind its declared proxy class -- run after the
     * install loop on both lanes, so the resolution lanes start
     * answering proxies the moment the boot hands the container over.
     *
     * @param list<ServiceDefinition> $definitions the planned set the lane installed
     */
    public static function marks(Container $container, array $definitions): void
    {
        foreach ($definitions as $definition) {
            if ($definition->lazyClass === null) {
                continue;
            }

            $container->markLazy($definition->id, $definition->lazyClass);
        }
    }
}
