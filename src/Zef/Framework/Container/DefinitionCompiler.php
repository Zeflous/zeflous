<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;
use ReflectionClass;

/**
 * Compiles a validated definition into the container's factory form.
 *
 * The output is a plain `Closure(Container): mixed` -- the exact signature
 * {@see Container::set()} already accepts -- so the declarative format
 * installs through the container's existing public surface and inherits its
 * memoisation semantics unchanged. Nothing about resolution is new: the
 * compiler only translates the declaration, dispatching each shape to its
 * closure builder ({@see FactoryClosures} for the factory arm).
 *
 * The instantiation seam is `ReflectionClass::newInstanceArgs()` -- the
 * standard container mechanism for a declared class with declared arguments
 * (no constructor analysis: that reflection-based resolution belongs to the
 * autowiring lane, ROADMAP area 2). Paid once per service thanks to the
 * container's memoisation; the compiler-pass lane (area 8) will replace it
 * with the dumped `new` literal.
 */
final readonly class DefinitionCompiler
{
    /**
     * @return Closure(Container): mixed
     */
    public function compile(ServiceDefinition $serviceDefinition): Closure
    {
        return match (true) {
            $serviceDefinition instanceof InstanceDefinition => $this->instantiating($serviceDefinition),
            $serviceDefinition instanceof FactoryDefinition => FactoryClosures::build($serviceDefinition),
            $serviceDefinition instanceof AliasDefinition => $this->aliasing($serviceDefinition->target),
            $serviceDefinition instanceof ValueDefinition => $this->holding($serviceDefinition->value),
            default => throw new DefinitionException(
                "The service '" . $serviceDefinition->id . "' carries an unsupported definition shape.",
            ),
        };
    }

    /**
     * @return Closure(Container): object
     */
    private function instantiating(InstanceDefinition $instanceDefinition): Closure
    {
        $class = $instanceDefinition->class;
        $arguments = $instanceDefinition->arguments;

        return static fn (Container $container): object => new ReflectionClass($class)
            ->newInstanceArgs(DefinitionArguments::resolve($arguments, $container))
        ;
    }

    /**
     * @return Closure(Container): mixed
     */
    private function aliasing(string $target): Closure
    {
        return static fn (Container $container): mixed => $container->get($target);
    }

    /**
     * @return Closure(): mixed
     */
    private function holding(mixed $value): Closure
    {
        return static fn (): mixed => $value;
    }
}
