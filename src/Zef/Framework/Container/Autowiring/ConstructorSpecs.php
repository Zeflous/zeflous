<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use ReflectionClass;

/**
 * The instantiable verdict and the one reflection read a class-shaped
 * declaration pays (the compile pass's and the reference arms' shared
 * class gate).
 *
 * An interface, abstract class, enum or private constructor parading as
 * a `class` shape is the boot-time refusal it always deserved to be;
 * everything that survives answers its constructor's pure spec list --
 * the one reflection read the lane ever pays for the class.
 */
final readonly class ConstructorSpecs
{
    /**
     * The immutable is-instantiable verdict for a class-shaped
     * declaration: a definition that survives boot can only fail at
     * instantiation through its arguments' references -- never through
     * an interface, an abstract class, an enum or a private constructor
     * parading as a `class` shape.
     */
    public static function instantiable(string $class): bool
    {
        if (class_exists($class) || interface_exists($class)) {
            return new ReflectionClass($class)->isInstantiable();
        }

        throw new AutowiringException(
            "Cannot plan the constructor of the unknown class '{$class}'.",
        );
    }

    /**
     * Whether the identifier names a class the lane may birth: an
     * existing, instantiable class (anything else -- an unregistered
     * id, an interface, a non-class -- is not birthable, and the caller
     * refuses it as unbound).
     */
    public static function birthable(string $class): bool
    {
        if (class_exists($class)) {
            return self::instantiable($class);
        }

        return false;
    }

    /**
     * @return list<AutowireParameterSpec>
     */
    public static function of(string $class, ReflectionMetadataExtractor $reflectionMetadataExtractor): array
    {
        if (self::instantiable($class)) {
            return $reflectionMetadataExtractor->extract($class);
        }

        throw new AutowiringException(
            "Cannot construct the service for '{$class}': the class is not instantiable "
            . '(an interface, abstract class, enum, or private constructor).',
        );
    }
}
