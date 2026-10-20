<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use ReflectionNamedType;
use ReflectionType;

/**
 * The lazy factory grammar's return half: the declared return type's
 * shape, narrowed to the one spelling a native lazy proxy can stand
 * behind -- exactly one named, non-builtin, resolvable class.
 *
 * Every unnameable spelling refuses with the way out in the message
 * ({@see LazyRefusals} carries each): no declared return type at all,
 * a union or intersection, a nullable, a builtin, the unresolved
 * `self`/`static` spellings, and a named class the runtime does not
 * carry. A concrete declared class is accepted even when the factory
 * answers a subclass: the proxy is an instance of the declared class
 * (PHP itself refuses, at materialisation, a factory answer that is
 * not an instance of it), so consumers type-hinted against the
 * declaration stay honest.
 */
final readonly class LazyReturnTypes
{
    /**
     * Answers the declared return class the proxy stands behind.
     *
     * @param string              $factory    the validated `'Target::method'` spelling
     * @param null|ReflectionType $reflectionType the reflected declaration, null when
     *                                        the factory declares no return type
     *
     * @return class-string the declared return class
     */
    public static function named(string $id, string $factory, ?ReflectionType $reflectionType): string
    {
        if (!$reflectionType instanceof \ReflectionType) {
            throw LazyRefusals::returnUndeclared($id, $factory);
        }

        if (!$reflectionType instanceof ReflectionNamedType) {
            throw LazyRefusals::returnNotOneClass($id, $factory, 'a union or intersection type');
        }

        if ($reflectionType->allowsNull()) {
            throw LazyRefusals::returnNotOneClass($id, $factory, 'a nullable type');
        }

        if ($reflectionType->isBuiltin()) {
            throw LazyRefusals::returnNotOneClass($id, $factory, 'a builtin type');
        }

        // The declared spelling, unresolved: reflection keeps `self`
        // and `static` as literal names, and they are the spellings the
        // declaration must not carry.
        $spelled = (string) $reflectionType;

        if ($spelled === 'self' || $spelled === 'static') {
            throw LazyRefusals::returnSelfSpelled($id, $factory, $spelled);
        }

        $class = $reflectionType->getName();

        if (!class_exists($class) && !interface_exists($class)) {
            throw LazyRefusals::returnClassUnknown($id, $factory, $class);
        }

        return $class;
    }
}
