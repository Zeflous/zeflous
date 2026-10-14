<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The container edges a definition declares, extracted for the boot graph.
 *
 * Edges are the explicit service references a definition carries: an alias
 * target, the `['service' => id]` argument references, and a factory target
 * -- the latter only when the target is *not* an existing class, because a
 * class-named factory target is a static call rather than a container edge.
 * The precedence matches {@see DefinitionCompiler}: a registered service
 * identifier shadows an equally-named class.
 */
final readonly class DefinitionReferences
{
    /**
     * @return list<string>
     */
    public static function of(ServiceDefinition $serviceDefinition): array
    {
        if ($serviceDefinition instanceof AliasDefinition) {
            return [$serviceDefinition->target];
        }

        if ($serviceDefinition instanceof InstanceDefinition) {
            return self::argumentReferences($serviceDefinition->arguments);
        }

        if ($serviceDefinition instanceof FactoryDefinition) {
            [$target] = explode('::', $serviceDefinition->factory);

            if (class_exists($target)) {
                // A class-named factory target is the static-call form: not
                // a container edge. A same-named registered service still
                // wins at call time (the compiler's precedence), so the
                // static fallback exists even when an edge was skipped.
                return self::argumentReferences($serviceDefinition->arguments);
            }

            return [$target, ...self::argumentReferences($serviceDefinition->arguments)];
        }

        return [];
    }

    /**
     * @param list<mixed> $arguments
     *
     * @return list<string>
     */
    private static function argumentReferences(array $arguments): array
    {
        $candidates = array_map(
            static function (mixed $argument): ?string {
                if (
                    \is_array($argument)
                    && \array_key_exists('service', $argument)
                    && \is_string($argument['service'])
                ) {
                    return $argument['service'];
                }

                return null;
            },
            $arguments,
        );

        return array_values(array_filter(
            $candidates,
            static fn (?string $candidate): bool => $candidate !== null,
        ));
    }
}
