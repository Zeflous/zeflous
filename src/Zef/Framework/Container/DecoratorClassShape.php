<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decorator entry grammar's class proof (the {@see InstanceShape}
 * posture, narrowed to the entry grammar): the wrapper's class, proven
 * a non-empty string naming an existing class at parse time -- a
 * decorator that survives boot cannot fail through a missing class.
 */
final readonly class DecoratorClassShape
{
    /**
     * The class proof, the instance shape's own parse-time discipline.
     *
     * @return class-string
     */
    public static function existingClass(string $target, mixed $class): string
    {
        if (!\is_string($class) || $class === '') {
            throw new DefinitionException(
                "The decorator for '{$target}' class must be a non-empty class name, "
                . get_debug_type($class) . ' given.',
            );
        }

        if (!class_exists($class)) {
            throw new DefinitionException(
                "The decorator for '{$target}' class '{$class}' does not exist.",
            );
        }

        return $class;
    }
}
