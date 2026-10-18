<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The contextual-binding lane's declaration check: does a planned
 * definition declare the dependency -- an argument or a method call
 * referencing the identifier?
 *
 * The check walks both reference realms ({@see ContextualReference} for
 * the entry-level form); an alias or value definition declares nothing.
 */
final readonly class ContextualDeclarations
{
    /**
     * Does the definition declare the dependency?
     */
    public static function declares(ServiceDefinition $serviceDefinition, string $dep): bool
    {
        return match (true) {
            $serviceDefinition instanceof InstanceDefinition,
            $serviceDefinition instanceof FactoryDefinition => self::carries(
                $serviceDefinition,
                $dep,
            ),
            default => false,
        };
    }

    /**
     * Does either reference realm of a planned declaration carry the
     * dependency -- its arguments, or its method calls?
     */
    private static function carries(FactoryDefinition|InstanceDefinition $serviceDefinition, string $dep): bool
    {
        return self::argumentsDeclare($serviceDefinition->arguments, $dep)
            || self::callsDeclare($serviceDefinition->calls, $dep);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function argumentsDeclare(array $arguments, string $dep): bool
    {
        return array_any(
            $arguments,
            static fn (mixed $argument): bool => ContextualReference::names($argument, $dep),
        );
    }

    /**
     * @param list<MethodCall> $calls
     */
    private static function callsDeclare(array $calls, string $dep): bool
    {
        return array_any(
            $calls,
            static fn (MethodCall $methodCall): bool => self::argumentsDeclare($methodCall->arguments, $dep),
        );
    }
}
