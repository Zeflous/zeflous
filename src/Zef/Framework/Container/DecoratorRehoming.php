<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration lane's re-homing: the original declaration moved under
 * its synthetic base identifier -- the same shape, the same arguments,
 * the same lifetime and method calls, and no tags, because the tags
 * travel with the original identifier (which the outermost wrapper
 * takes over). The reference container's ZEF-DEEP-08: a re-homed base
 * that kept its tags silently indexed the *undecorated* inner instance
 * under the tag, and the tag locator answered it.
 */
final readonly class DecoratorRehoming
{
    /**
     * Answers the definition re-homed under the synthetic base
     * identifier, tags stripped.
     */
    public static function rehomed(ServiceDefinition $serviceDefinition, string $baseId): ServiceDefinition
    {
        return match (true) {
            $serviceDefinition instanceof InstanceDefinition => new InstanceDefinition(
                $baseId,
                $serviceDefinition->class,
                $serviceDefinition->arguments,
                $serviceDefinition->lifetime,
                new ServiceTags(),
                $serviceDefinition->calls,
            ),
            $serviceDefinition instanceof FactoryDefinition => new FactoryDefinition(
                $baseId,
                $serviceDefinition->factory,
                $serviceDefinition->arguments,
                $serviceDefinition->lifetime,
                new ServiceTags(),
                $serviceDefinition->calls,
            ),
            $serviceDefinition instanceof AliasDefinition => new AliasDefinition(
                $baseId,
                $serviceDefinition->target,
                $serviceDefinition->lifetime,
            ),
            $serviceDefinition instanceof ValueDefinition => new ValueDefinition(
                $baseId,
                $serviceDefinition->value,
            ),
            default => throw new DefinitionException(
                "The '{$serviceDefinition->id}' definition shape cannot be re-homed -- "
                . 'only the four declarative shapes decorate.',
            ),
        };
    }
}
