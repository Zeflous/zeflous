<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception entry grammar's class proof (the
 * {@see DecoratorClassShape} posture, shared by both entry-carrying
 * knobs): the builder's class, proven a non-empty string naming an
 * existing class at parse time -- an interceptor or listener that
 * survives boot cannot fail through a missing class.
 */
final readonly class InterceptionClassShape
{
    /**
     * The class proof, the instance shape's own parse-time discipline.
     *
     * @return class-string
     */
    public static function existingClass(string $label, mixed $class): string
    {
        if (!\is_string($class) || $class === '') {
            throw InterceptionRefusals::classNotANonEmptyString($label, $class);
        }

        if (!class_exists($class)) {
            throw InterceptionRefusals::classDoesNotExist($label, $class);
        }

        return $class;
    }
}
