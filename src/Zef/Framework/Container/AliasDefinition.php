<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The alias shape: a transparent pointer to another service identifier.
 *
 * Interface-to-implementation mapping is the primary use (bind an abstraction
 * to the concrete service), with the target proven resolvable at boot by the
 * {@see ServiceDefinitionGraph}.
 *
 * The declaration grammar keeps the shape lifetime-less (an alias is
 * transparent: it rides its target's resolution). The value object's
 * optional third constructor argument exists for the contextual-binding
 * lane's synthetic alias alone: `@contextual:<consumer>|<dep>` carries the
 * consumer's lifetime so the boot graph's capture analysis and the store's
 * memoisation both see the rewritten chain exactly the way a direct
 * consumer-to-target reference would behave.
 */
final readonly class AliasDefinition extends ServiceDefinition
{
    public function __construct(
        string $id,
        public string $target,
        ServiceLifetime $serviceLifetime = ServiceLifetime::SINGLETON,
    ) {
        if ($target === '') {
            throw new DefinitionException("Service '{$id}' alias target must not be empty.");
        }

        parent::__construct($id, $serviceLifetime);
    }
}
