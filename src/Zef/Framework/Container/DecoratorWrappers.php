<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration lane's chain fold: the wrapper list one target's
 * declared chain builds -- the outermost wrapper under the original
 * identifier (the only definition the rest of the set ever observes,
 * carrying the declaration's tags), and the between-layer wrappers
 * under their synthetic identifiers (carrying none).
 *
 * Each layer references the next layer inward through the ordinary
 * service-reference grammar, so the chain is a path of real edges in
 * the rewritten set's reference graph -- the boot's cycle walk,
 * lifetime captures and tag index all see it exactly the way they see
 * any direct reference.
 */
final readonly class DecoratorWrappers
{
    /**
     * The outermost wrapper: the chain's first entry under the target's
     * original identifier, carrying the target's tags.
     *
     * @param list<DecoratorEntry> $chain the parsed chain, first entry outermost
     */
    public static function outermost(ServiceDefinition $serviceDefinition, array $chain): ServiceDefinition
    {
        return DecoratorRewrites::wrapper(
            $chain[0],
            $serviceDefinition->id,
            DecoratorLayers::innerOf($serviceDefinition->id, 0, \count($chain)),
            $serviceDefinition,
            $serviceDefinition->tags,
        );
    }

    /**
     * The between-layer wrappers (k = 1..count-1), each under its
     * synthetic identifier, referencing the next layer inward, carrying
     * no tags -- the tag belongs to the original identifier alone.
     *
     * @param list<DecoratorEntry> $chain the parsed chain, first entry outermost
     *
     * @return list<ServiceDefinition>
     */
    public static function inner(ServiceDefinition $serviceDefinition, array $chain): array
    {
        $wrappers = [];
        for ($k = 1, $count = \count($chain); $k < $count; ++$k) {
            $wrappers[] = DecoratorRewrites::wrapper(
                $chain[$k],
                DecoratorLayers::layer($serviceDefinition->id, $k),
                DecoratorLayers::innerOf($serviceDefinition->id, $k, $count),
                $serviceDefinition,
                new ServiceTags(),
            );
        }

        return $wrappers;
    }
}
