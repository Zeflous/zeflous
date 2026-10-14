<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The edge collection of a definition set -- the boot graph's first half
 * (see {@see ServiceDefinitionGraph} for the cycle walk).
 *
 * For every definition, the explicit references it declares are resolved
 * into edges: an unknown target -- one that is neither another definition
 * nor a service the container already carries -- is rejected here, while a
 * reference into a closure-registered service is a leaf edge (the host
 * application's own behaviour, exactly as it is without the declarative
 * format) and is skipped: only definition-to-definition edges can cycle.
 */
final readonly class DefinitionEdges
{
    /**
     * @param list<ServiceDefinition> $definitions
     *
     * @return array<string, list<string>>
     */
    public static function collect(array $definitions, Container $container): array
    {
        $byId = [];
        foreach ($definitions as $definition) {
            $byId[$definition->id] = $definition;
        }

        $edges = [];
        foreach ($definitions as $definition) {
            foreach (DefinitionReferences::of($definition) as $target) {
                if (!isset($byId[$target]) && !$container->has($target)) {
                    throw new DefinitionException(
                        "Service '{$definition->id}' references the unknown service '{$target}'.",
                    );
                }

                if (!isset($byId[$target])) {
                    continue;
                }

                $edges[$definition->id][] = $target;
            }
        }

        return $edges;
    }
}
