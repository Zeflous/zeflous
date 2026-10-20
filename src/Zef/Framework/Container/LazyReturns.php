<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The factory shape's lazy proxy class: the declared return type of
 * the factory the declaration defers behind, answered through the
 * grammar's own two halves -- the target's parse-time mirrors
 * ({@see LazyTargets}) and the declared return's shape
 * ({@see LazyReturnTypes}).
 *
 * A factory may answer any value, but a native lazy proxy is an
 * instance of one class ({@see LazyConstructions}), so the lazy
 * factory declaration must be able to *name* that class at parse
 * time -- and the only honest source is the factory method's own
 * declared return type. The grammar each half enforces is deliberately
 * narrow, every refusal spelling what the declaration must do instead
 * ({@see LazyRefusals} carries each message).
 */
final readonly class LazyReturns
{
    /**
     * Answers the factory method's declared return class.
     *
     * @param string $factory the validated `'Target::method'` spelling
     *
     * @return class-string the declared return class
     */
    public static function classOf(string $id, string $factory): string
    {
        return LazyReturnTypes::named(
            $id,
            $factory,
            LazyTargets::reflected($id, $factory)->getReturnType(),
        );
    }

    /**
     * The factory shape's lazy proxy class: null when the declaration
     * does not ask to defer, the factory method's declared return
     * class when it does, proven instantiable -- the request itself
     * ({@see LazyDeclarations::deferred()}) the shell both shapes
     * spell identically.
     *
     * @param array<array-key, mixed> $entry
     *
     * @return null|class-string the proxy class, or null when the
     *                           declaration builds eagerly
     */
    public static function behindTheDeclaredReturn(
        string $id,
        array $entry,
        ServiceLifetime $serviceLifetime,
        string $factory,
    ): ?string {
        return LazyDeclarations::deferred($id, $entry, $serviceLifetime)
            ? LazyClasses::of($id, self::classOf($id, $factory))
            : null;
    }
}
