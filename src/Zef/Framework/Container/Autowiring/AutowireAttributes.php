<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use ReflectionParameter;

/**
 * The compile-phase attribute reader: the single `#[Inject]` or
 * `#[Target]` a constructor parameter carries, or null when it carries
 * none (see {@see Inject} and {@see Target} for the attributes' own
 * grammar).
 *
 * A doubled attribute is the ambiguity it is: surfaced with the
 * parameter's name, never guessed into a precedence. Reading through
 * the attributes' own constructors means a malformed attribute fails
 * closed here, at the read, with the attribute's own message.
 */
final readonly class AutowireAttributes
{
    public static function injectId(ReflectionParameter $reflectionParameter): ?string
    {
        $found = $reflectionParameter->getAttributes(Inject::class);
        if ($found === []) {
            return null;
        }

        if (\count($found) > 1) {
            throw new AutowiringException(
                "Parameter '\${$reflectionParameter->getName()}' carries more than one Inject attribute.",
            );
        }

        return $found[0]->newInstance()->id;
    }

    public static function targetClass(ReflectionParameter $reflectionParameter): ?string
    {
        $found = $reflectionParameter->getAttributes(Target::class);
        if ($found === []) {
            return null;
        }

        if (\count($found) > 1) {
            throw new AutowiringException(
                "Parameter '\${$reflectionParameter->getName()}' carries more than one Target attribute.",
            );
        }

        return $found[0]->newInstance()->class;
    }
}
