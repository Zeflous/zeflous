<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

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
 * container's memoisation for the singleton lifetime -- and for every other
 * resolution the reflection itself is memoised ({@see ReflectionMemo},
 * ROADMAP area 11's "reflection caching": a transient's second `get()` pays
 * a map lookup, not a fresh reflection construction); the compiler-pass
 * lane (area 8) replaces the whole seam with the dumped `new` literal. A
 * declaration carrying `calls` has them applied to the built instance
 * through {@see MethodCallInvocations::apply()} -- the method-call injection
 * lane (area 4), an opt-in extension whose absent key pays only the empty
 * call list's loop guard.
 *
 * An unplanned definition reaching this compiler is a misuse of the
 * seam: the autowiring lane's declarations are compiled by
 * {@see Autowiring\AutowireCompilerPass}
 * into the positional form first, so the refusal names the run the
 * definition never went through.
 */
final readonly class DefinitionCompiler
{
    public function __construct(
        private ReflectionMemo $reflectionMemo = new ReflectionMemo(),
    ) {
    }

    /**
     * @return Closure(Container): mixed
     */
    public function compile(ServiceDefinition $serviceDefinition): Closure
    {
        return match (true) {
            $serviceDefinition instanceof UnplannedInstanceDefinition => throw new DefinitionException(
                "The service '" . $serviceDefinition->id . "' carries an unplanned constructor plan -- "
                . 'run the autowire compiler pass before installation.',
            ),
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
        $calls = $instanceDefinition->calls;

        return fn (Container $container): object => MethodCallInvocations::apply(
            $this->reflectionMemo->reflect($class)
                ->newInstanceArgs(DefinitionArguments::resolve($arguments, $container)),
            $calls,
            $container,
        );
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
