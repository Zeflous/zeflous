<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The boot's install loop: every planned definition compiled through
 * the {@see DefinitionCompiler}'s closure lane and registered under its
 * own lifetime -- one spelling of the loop both lanes' install halves
 * share a name for, split from the compile lane so the lane stays the
 * orchestration (the {@see ContainerRegistrations} posture: the
 * container-side installation surface is its own unit).
 */
final readonly class ContainerInstalls
{
    public function __construct(
        private DefinitionCompiler $definitionCompiler = new DefinitionCompiler(),
    ) {
    }

    /**
     * Installs every planned definition into the container.
     *
     * @param list<ServiceDefinition> $definitions
     */
    public function definitions(Container $container, array $definitions): void
    {
        foreach ($definitions as $definition) {
            $container->set($definition->id, $this->definitionCompiler->compile($definition), $definition->lifetime);
        }
    }
}
