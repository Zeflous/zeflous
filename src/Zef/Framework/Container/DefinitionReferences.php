<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The container edges a definition declares, extracted for the boot graph.
 *
 * Edges are the explicit service references a definition carries: an alias
 * target, the `['service' => id]` argument references, a factory target
 * -- the latter only when the target is *not* an existing class, because a
 * class-named factory target is a static call rather than a container edge
 * -- and the `['service' => id]` references of the method-call injection
 * list: a dependency injected through a call is a dependency all the same,
 * so its resolvability, its cycles and its lifetime captures are proven at
 * boot exactly like a constructor argument's. The precedence matches
 * {@see DefinitionCompiler}: a registered service identifier shadows an
 * equally-named class.
 */
final readonly class DefinitionReferences
{
    /**
     * @return list<string>
     */
    public static function of(ServiceDefinition $serviceDefinition): array
    {
        return match (true) {
            $serviceDefinition instanceof UnplannedInstanceDefinition => throw new DefinitionException(
                "Service '{$serviceDefinition->id}' carries an unplanned constructor plan -- "
                . 'run the autowire compiler pass before graph validation.',
            ),
            $serviceDefinition instanceof AliasDefinition => [$serviceDefinition->target],
            $serviceDefinition instanceof FactoryDefinition => self::factoryReferences($serviceDefinition),
            $serviceDefinition instanceof InstanceDefinition => self::carriedReferences(
                $serviceDefinition->arguments,
                $serviceDefinition->calls,
            ),
            default => [],
        };
    }

    /**
     * A factory's edges: the `['service' => id]` argument references plus
     * the factory target itself -- the latter only when the target is *not*
     * an existing class, because a class-named factory target is a static
     * call rather than a container edge.
     *
     * @return list<string>
     */
    private static function factoryReferences(FactoryDefinition $factoryDefinition): array
    {
        [$target] = explode('::', $factoryDefinition->factory);

        if (class_exists($target)) {
            // A class-named factory target is the static-call form: not
            // a container edge. A same-named registered service still
            // wins at call time (the compiler's precedence), so the
            // static fallback exists even when an edge was skipped.
            return self::carriedReferences($factoryDefinition->arguments, $factoryDefinition->calls);
        }

        return [
            $target,
            ...self::carriedReferences($factoryDefinition->arguments, $factoryDefinition->calls),
        ];
    }

    /**
     * The references a planned declaration carries across both realms:
     * its argument list and its method-call injection list.
     *
     * @param list<mixed>      $arguments
     * @param list<MethodCall> $calls
     *
     * @return list<string>
     */
    private static function carriedReferences(array $arguments, array $calls): array
    {
        $callArguments = array_map(
            static fn (MethodCall $methodCall): array => $methodCall->arguments,
            $calls,
        );

        return self::argumentReferences(array_merge($arguments, ...$callArguments));
    }

    /**
     * @param list<mixed> $arguments
     *
     * @return list<string>
     */
    private static function argumentReferences(array $arguments): array
    {
        $candidates = array_map(ArgumentNames::identifier(...), $arguments);

        return array_values(array_filter(
            $candidates,
            static fn (?string $candidate): bool => $candidate !== null,
        ));
    }
}
