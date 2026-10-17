<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use ReflectionMethod;
use ReflectionParameter;

/**
 * The spec assembly: one reflected constructor parameter becomes one
 * {@see AutowireParameterSpec} snapshot (see
 * {@see ReflectionMetadataExtractor} for the read itself).
 *
 * The defaults come straight from the declaration; the type carries the
 * {@see AutowireParameterTypes} classification; the attributes come
 * through {@see AutowireAttributes}' fail-closed read.
 */
final readonly class AutowireSpecFactory
{
    /**
     * A constructor's whole parameter list, in declaration order.
     *
     * @return list<AutowireParameterSpec>
     */
    public static function specs(ReflectionMethod $reflectionMethod): array
    {
        return array_map(
            static fn (ReflectionParameter $reflectionParameter): AutowireParameterSpec => self::specOf(
                $reflectionParameter,
            ),
            $reflectionMethod->getParameters(),
        );
    }

    private static function specOf(ReflectionParameter $reflectionParameter): AutowireParameterSpec
    {
        return new AutowireParameterSpec(
            name: $reflectionParameter->getName(),
            isVariadic: $reflectionParameter->isVariadic(),
            isOptional: $reflectionParameter->isOptional(),
            hasDefault: $reflectionParameter->isDefaultValueAvailable(),
            defaultValue: $reflectionParameter->isDefaultValueAvailable()
                ? $reflectionParameter->getDefaultValue()
                : null,
            className: AutowireParameterTypes::classNameOf($reflectionParameter),
            unsupportedTypeReason: AutowireParameterTypes::unsupportedTypeReason($reflectionParameter),
            injectId: AutowireAttributes::injectId($reflectionParameter),
            targetClass: AutowireAttributes::targetClass($reflectionParameter),
        );
    }
}
