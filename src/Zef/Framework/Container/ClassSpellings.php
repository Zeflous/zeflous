<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The class shape's own spelling validation: the declared class name
 * the `'class'` key carries, proven a non-empty string naming a class
 * the runtime actually has -- the class-shape mirror of the factory
 * shape's {@see FactorySpellings}, one unit for the grammar the shape
 * keeps.
 *
 * The refusal is fail-closed with the service named and the way out
 * in the message: a missing, non-string or empty spelling is a
 * declaration defect, and a class name the runtime does not carry
 * cannot be constructed at the first resolution either.
 */
final readonly class ClassSpellings
{
    /**
     * Answers the validated class name.
     *
     * @param mixed $class the raw `'class'` value from the definition map
     *
     * @return class-string the proven class name
     */
    public static function validated(string $id, mixed $class): string
    {
        if (!\is_string($class) || $class === '') {
            throw new DefinitionException(
                "Service '{$id}' class must be a non-empty class name, " . get_debug_type($class) . ' given.',
            );
        }

        if (!class_exists($class)) {
            throw new DefinitionException("Service '{$id}' class '{$class}' does not exist.");
        }

        return $class;
    }
}
