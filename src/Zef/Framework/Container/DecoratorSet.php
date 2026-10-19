<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration lane's set surgery: a planned definition set rewritten
 * with one target's chain applied -- the target's effective declaration
 * replaced by the outermost wrapper under the original identifier, the
 * original re-homed as the innermost layer, and the wrappers between
 * them appended, each referencing the next layer inward (the
 * {@see ContextualSet} posture: the surgery is the write half, the
 * guards live ahead of it).
 *
 * The rewritten set's reference graph contains real edges from the
 * original identifier down to the re-homed base, so every existing
 * guarantee of the boot holds unchanged: the graph walks the chain for
 * cycles, the lifetime captures see the chain exactly the way a direct
 * reference's would, and the tag index -- walked after this apply --
 * answers the decorated instance under the original identifier, because
 * the tags stay with the identifier and never travel down the chain
 * (ZEF-DEEP-08).
 */
final readonly class DecoratorSet
{
    /**
     * Answers the set with the target's chain applied.
     *
     * @param list<ServiceDefinition> $definitions
     * @param list<DecoratorEntry>    $chain       the parsed chain, first entry outermost
     *
     * @return list<ServiceDefinition>
     */
    public static function decorated(
        array $definitions,
        int $index,
        ServiceDefinition $serviceDefinition,
        array $chain,
    ): array {
        $spelled = DecoratorLayers::spellings($serviceDefinition->id, \count($chain));

        $rebuilt = [];
        foreach ($definitions as $position => $definition) {
            if (\in_array($definition->id, $spelled, true)) {
                throw DecoratorRefusals::collision($definition->id);
            }

            $rebuilt[] = $position === $index
                ? DecoratorWrappers::outermost($serviceDefinition, $chain)
                : $definition;
        }

        return [
            ...$rebuilt,
            DecoratorRehoming::rehomed($serviceDefinition, DecoratorLayers::base($serviceDefinition->id)),
            ...DecoratorWrappers::inner($serviceDefinition, $chain),
        ];
    }
}
