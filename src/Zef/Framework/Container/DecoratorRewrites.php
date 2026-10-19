<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration lane's per-definition wrapper construction: one
 * wrapper layer built from its decorator entry -- the entry's shape
 * instantiated with the entry's arguments (every inner reference
 * rewritten to the layer this wrapper sits over through
 * {@see DecoratorInner}), under the decorated declaration's lifetime.
 *
 * Every product is an ordinary definition by construction: the wrapper
 * is a class or factory shape whose arguments reference the next layer
 * inward through the plain service-reference grammar. Nothing
 * downstream -- the install loop, the boot graph, the AOT dumper --
 * learns that decoration happened.
 */
final readonly class DecoratorRewrites
{
    /**
     * One wrapper layer built from its decorator entry. Every layer of
     * a chain shares the decorated declaration's lifetime, so a
     * singleton chain is built once, a transient chain rebuilds whole,
     * and a request-scoped chain rides one scope.
     *
     * @param ServiceTags $serviceTags the tags this layer carries -- the
     *                                 outermost wrapper takes the original's, every inner layer none
     */
    public static function wrapper(
        DecoratorEntry $decoratorEntry,
        string $id,
        string $innerId,
        ServiceDefinition $serviceDefinition,
        ServiceTags $serviceTags,
    ): ServiceDefinition {
        $arguments = DecoratorInner::rewritten($decoratorEntry->arguments, $innerId);

        $class = $decoratorEntry->class;
        if ($class !== null) {
            return new InstanceDefinition($id, $class, $arguments, $serviceDefinition->lifetime, $serviceTags);
        }

        $factory = $decoratorEntry->factory;
        if ($factory === null) {
            throw new DefinitionException(
                'A decorator entry carries exactly one shape key -- '
                . 'hand-built entries must spell either class or factory.',
            );
        }

        return new FactoryDefinition($id, $factory, $arguments, $serviceDefinition->lifetime, $serviceTags);
    }
}
