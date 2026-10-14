<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The alias shape: a transparent pointer to another service identifier.
 *
 * Interface-to-implementation mapping is the primary use (bind an abstraction
 * to the concrete service), with the target proven resolvable at boot by the
 * {@see ServiceDefinitionGraph}.
 */
final readonly class AliasDefinition extends ServiceDefinition
{
    public function __construct(
        string $id,
        public string $target,
    ) {
        if ($target === '') {
            throw new DefinitionException("Service '{$id}' alias target must not be empty.");
        }

        parent::__construct($id);
    }
}
