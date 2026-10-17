<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use ReflectionClass;

/**
 * The compile-phase reflection reader: one class constructor, one pass,
 * one list of {@see AutowireParameterSpec} snapshots.
 *
 * Reflection lives HERE and only here (the github v2.9 compile-pass
 * discipline this lane ports): the planner, the graph and the runtime
 * resolution all consume the pure specs, so the reflection cost of a
 * definition is paid exactly once per boot -- never per resolution, and
 * never again for a class whose spec is already extracted. Union and
 * intersection types answer their honest unsupported-reason (a parameter
 * admitting several types names no single binding); attributes are read
 * through {@see AutowireAttributes}, their own constructors validating
 * the declarations.
 */
final readonly class ReflectionMetadataExtractor
{
    /**
     * @return list<AutowireParameterSpec>
     */
    public function extract(string $class): array
    {
        $constructor = $this->reflect($class)->getConstructor();
        if ($constructor === null) {
            return [];
        }

        return AutowireSpecFactory::specs($constructor);
    }

    /**
     * The immutable is-instantiable verdict for a class-shaped
     * declaration: a definition that survives boot can only fail at
     * instantiation through its arguments' references -- never through
     * an interface, an abstract class, an enum or a private constructor
     * parading as a `class` shape.
     */
    public function isInstantiable(string $class): bool
    {
        return $this->reflect($class)->isInstantiable();
    }

    /**
     * The one reflection construction, behind the shared unknown-class
     * refusal: reflection's own exception is never the answer a
     * declaration deserves -- the class is named in the message.
     */
    /**
     * @return ReflectionClass<object>
     */
    private function reflect(string $class): ReflectionClass
    {
        if (class_exists($class) || interface_exists($class)) {
            return new ReflectionClass($class);
        }

        throw new AutowiringException(
            "Cannot plan the constructor of the unknown class '{$class}'.",
        );
    }
}
