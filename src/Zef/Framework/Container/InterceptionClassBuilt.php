<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use ReflectionClass;

/**
 * The interception entry's class-shape construction (the definition
 * grammar's own instantiation idiom, narrowed to what an entry is):
 * the constructor fit proven first ({@see InterceptionClassFit} --
 * too many declared arguments are a refusal the engine's own
 * `newInstanceArgs` would have silently swallowed, stopping before a
 * required parameter names the parameter), then the instantiation over
 * the resolved arguments.
 *
 * The reflection is boot-only, read once per entry -- the same
 * discipline the autowiring lane keeps for its own constructor reads.
 */
final readonly class InterceptionClassBuilt
{
    /**
     * Builds the class-shaped entry's object.
     *
     * @param list<mixed>  $arguments the entry's validated argument list
     * @param class-string $class     the entry's proven class
     */
    public static function built(string $label, array $arguments, string $class, Container $container): object
    {
        InterceptionClassFit::asserted($label, $arguments, $class);

        return new ReflectionClass($class)
            ->newInstanceArgs(DefinitionArguments::resolve($arguments, $container))
        ;
    }
}
