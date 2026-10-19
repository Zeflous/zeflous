<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One decoration's application to a planned definition set (see
 * {@see Decorators} for the walk that runs this per target -- the walk
 * owns the namespace guard, once, over the set before any surgery, so
 * the lane's own synthetic identifiers from an earlier target are never
 * mistaken for a squatted declaration).
 *
 * The application answers the reference container's own freeze-time
 * step, ported to the compile pipeline's ordering: guards first -- the
 * target declared (its effective declaration located) and planned (the
 * autowire pass belongs ahead of this apply) -- then the set surgery
 * itself ({@see DecoratorSet}, which guards this target's own
 * spellings).
 *
 * The unplanned guard mirrors the contextual lane's for the same
 * reason: the apply runs over a planned set, and a hand-driven misuse
 * that feeds it an {@see UnplannedInstanceDefinition} would silently
 * re-home a definition the autowire pass has not finished with.
 */
final readonly class DecoratorApplication
{
    /**
     * Applies one target's chain: guards, then the set surgery.
     *
     * @param list<ServiceDefinition> $definitions
     * @param list<DecoratorEntry>    $chain       the parsed chain, first entry outermost
     *
     * @return list<ServiceDefinition>
     */
    public static function applied(array $definitions, string $target, array $chain): array
    {
        [$index, $definition] = DecoratorTarget::effective($definitions, $target);

        if ($definition instanceof UnplannedInstanceDefinition) {
            throw DecoratorRefusals::unplanned($target);
        }

        return DecoratorSet::decorated($definitions, $index, $definition, $chain);
    }
}
