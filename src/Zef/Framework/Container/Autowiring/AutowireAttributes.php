<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use ReflectionClass;
use ReflectionParameter;

/**
 * The compile-phase attribute reader: the single `#[Inject]`, `#[Target]`
 * or `#[Value]` a constructor parameter carries, or null when it carries
 * none (see {@see Inject}, {@see Target} and {@see Value} for the
 * attributes' own grammars).
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
        return self::attribute($reflectionParameter, Inject::class)?->id;
    }

    public static function targetClass(ReflectionParameter $reflectionParameter): ?string
    {
        return self::attribute($reflectionParameter, Target::class)?->class;
    }

    /**
     * The `#[Value]` attribute's parameter-address key, or null when
     * the parameter carries none.
     */
    public static function valueKey(ReflectionParameter $reflectionParameter): ?string
    {
        return self::attribute($reflectionParameter, Value::class)?->key;
    }

    /**
     * The parameter's single attribute of the given class, constructed
     * through its own constructor (the fail-closed read), or null.
     *
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return null|T
     */
    private static function attribute(ReflectionParameter $reflectionParameter, string $attribute): ?object
    {
        $found = $reflectionParameter->getAttributes($attribute);

        if ($found === []) {
            return null;
        }

        if (\count($found) > 1) {
            throw new AutowiringException(
                "Parameter '\${$reflectionParameter->getName()}' carries more than one "
                . self::shortName($attribute) . ' attribute.',
            );
        }

        return $found[0]->newInstance();
    }

    /**
     * The attribute's own name, for the doubled-attribute refusal.
     *
     * @param class-string $attribute
     */
    private static function shortName(string $attribute): string
    {
        return new ReflectionClass($attribute)->getShortName();
    }
}
