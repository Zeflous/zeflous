<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration lane's boot apply (ROADMAP, "CONTAINER" area 7): the
 * walk over the knob's parsed chains, each one applied to the planned
 * definition set by {@see DecoratorApplication} between the
 * contextual-binding apply and the graph validation.
 *
 * The mechanism is the reference container's own ported insight
 * (`DecoratorApplier`, github v2.9), kept whole and adapted to the
 * declarative grammar: the wrapper chain is *definition surgery at
 * compile time* -- the target re-homed under a synthetic identifier,
 * the decorators wrapped around it as ordinary definitions -- so every
 * existing guarantee of the boot graph holds unchanged for the
 * rewritten set, the install loop installs the chain like any other
 * declaration, and the AOT dump exports it for free.
 *
 * The walk owns the namespace guard deliberately: it runs once, over
 * the set *before* any surgery, so the lane's own synthetic identifiers
 * -- which later targets would legitimately see -- are never mistaken
 * for a squatted host declaration.
 */
final readonly class Decorators
{
    /**
     * Applies every parsed chain to the planned definition set and
     * answers the rewritten set (the re-homed bases and wrappers
     * included). An empty map answers the set unchanged -- the no-op
     * costs no walk, and the namespace reservation below is the lane's
     * own concern, so it binds only when the lane runs.
     *
     * @param list<ServiceDefinition> $definitions
     *
     * @return list<ServiceDefinition>
     */
    public static function apply(array $definitions, DecoratorMap $decoratorMap): array
    {
        if ($decoratorMap->decorations() === []) {
            return $definitions;
        }

        foreach ($definitions as $definition) {
            if (DecoratorLayers::occupies($definition->id)) {
                throw DecoratorRefusals::namespaceOccupied($definition->id);
            }
        }

        $applied = $definitions;
        foreach ($decoratorMap->decorations() as $target => $chain) {
            $applied = DecoratorApplication::applied($applied, $target, $chain);
        }

        return $applied;
    }
}
