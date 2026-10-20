<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use ReflectionClass;

/**
 * The lazy lane's proxy-class validation: the class a lazy declaration
 * defers behind must be a class PHP itself can instantiate a native
 * lazy proxy of ({@see LazyConstructions}).
 *
 * The check is the grammar's own, run at parse time so a declaration
 * that survives boot can only fail at materialisation through its own
 * construction -- never through a proxy PHP refused to create: an
 * abstract class, an interface or an enum cannot carry a lazy proxy
 * (there is no instance of it to forward to), and `readonly` and
 * `final` classes can ({@see LazyConstructions}' own docblock spells
 * the mechanism). The refusal names the service, the class and the
 * reason ({@see LazyRefusals::classNotInstantiable()}).
 */
final readonly class LazyClasses
{
    /**
     * Answers the proxy class a lazy declaration defers behind, or
     * refuses it.
     *
     * @param class-string $class the candidate proxy class
     *
     * @return class-string the same class, proven instantiable
     */
    public static function of(string $id, string $class): string
    {
        if (!new ReflectionClass($class)->isInstantiable()) {
            throw LazyRefusals::classNotInstantiable($id, $class);
        }

        return $class;
    }
}
