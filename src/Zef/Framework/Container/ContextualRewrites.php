<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The contextual-binding lane's reference rewrite dispatch (the write
 * half of the {@see ContextualBindings} apply).
 *
 * The rewritten definition is the declaration's own
 * `withRewrittenReferences()` answer -- every reference to the
 * dependency substituted with the synthetic identifier the binding
 * flows through, the value object rebuilt verbatim otherwise. A shape
 * that carries no reference realms of its own is the honest refusal:
 * there is nothing the binding could override.
 */
final readonly class ContextualRewrites
{
    /**
     * The definition with every reference to the dependency rewritten to
     * the synthetic identifier.
     */
    public static function rewritten(ServiceDefinition $serviceDefinition, string $dep, string $via): ServiceDefinition
    {
        return match (true) {
            $serviceDefinition instanceof InstanceDefinition => $serviceDefinition->withRewrittenReferences($dep, $via),
            $serviceDefinition instanceof FactoryDefinition => $serviceDefinition->withRewrittenReferences($dep, $via),
            default => throw ContextualRefusals::dependencyNotDeclared($serviceDefinition->id, $dep),
        };
    }
}
