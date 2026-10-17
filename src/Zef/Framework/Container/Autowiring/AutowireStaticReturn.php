<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use ReflectionMethod;
use ReflectionNamedType;

/**
 * The declared return type one static method proves -- the reflection
 * half of the factory product's inference (see
 * {@see AutowireFactoryProduct} for the spelling that reaches here).
 *
 * Only the static form with a single non-built-in return type answers:
 * a non-static method is not the static call the spelling makes, a
 * missing method is the declaration's own late failure (not this
 * lane's), and a union, intersection, built-in or absent return type
 * names no class the collection could gather.
 */
final readonly class AutowireStaticReturn
{
    /**
     * @param class-string $target the class the static spelling names
     */
    public static function of(string $target, string $method): ?string
    {
        if (!method_exists($target, $method)) {
            return null;
        }

        $reflectionMethod = new ReflectionMethod($target, $method);
        if (!$reflectionMethod->isStatic()) {
            return null;
        }

        $returnType = $reflectionMethod->getReturnType();
        if (!$returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
            return null;
        }

        return $returnType->getName();
    }
}
