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
 *
 * One edge is not declared but derived: a class-named factory target whose
 * identifier the planned set also carries resolves as a service at call
 * time (the runtime precedence -- a registered identifier shadows an
 * equally-named class), so the edge the resolution will actually walk is
 * an edge the graph proves here, its cycles and its lifetime captures
 * exactly like a declared reference's -- the judgment its own unit
 * ({@see FactoryShadows}), the #783 audit's finding: the runtime used to
 * discover what the boot never walked.
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
                if (isset($byId[$target])) {
                    $edges[$definition->id][] = $target;

                    continue;
                }

                if (!$container->has($target)) {
                    throw new DefinitionException(
                        "Service '{$definition->id}' references the unknown service '{$target}'.",
                    );
                }
            }

            // The derived edge: the class-named factory target the planned
            // set carries resolves as a service at call time, so its edge is
            // collected beside the declared references -- the static-call
            // spelling only ever runs for a target no planned definition
            // owns ({@see FactoryShadows} keeps the judgment).
            $shadow = FactoryShadows::target($definition, $byId);

            if ($shadow === null) {
                continue;
            }

            $edges[$definition->id][] = $shadow;
        }

        return $edges;
    }
}
