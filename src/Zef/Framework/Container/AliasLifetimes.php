<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The alias-lifetime alignment stage of the compile pipeline: every
 * ordinary alias rewritten to its target's sharing policy, the one
 * surgery that makes the grammar's own refusal message -- "an alias
 * is transparent: it rides the lifetime of its target"
 * ({@see AliasShape}) -- the truth the boot installs.
 *
 * The bug this stage closes (#769): the shape parser answers the alias
 * lifetime-less (a `lifetime` key is refused with that transparency
 * claim), and the install loop then registers the alias's closure under
 * the default singleton lane -- a *second* memo on top of the target's
 * own resolution, pinning the first answer of a transient target
 * forever. The refusal message and the behaviour were opposites; this
 * stage makes the behaviour the message.
 *
 * This unit is the stage's own fold -- which definitions the walk
 * touches and which stay untouched -- while the questions the walk
 * itself answers live in their own units: one alias's ride through the
 * set ({@see AliasChains}) and one target's policy ({@see
 * AliasTargets}). The stage sits between the contextual apply and the
 * decoration apply on purpose: a decorated alias's wrapper chain and
 * re-homed base are built from the declaration's lifetime, so the
 * alignment must land first for every layer of the chain to carry the
 * target's policy consistently.
 */
final readonly class AliasLifetimes
{
    /**
     * Aligns every ordinary alias in the planned set to its target's
     * sharing policy and answers the rewritten set -- the same list,
     * the same order, the same definitions except the aliases whose
     * ride changed.
     *
     * @param list<ServiceDefinition> $definitions
     *
     * @return list<ServiceDefinition>
     */
    public static function align(array $definitions, Container $container): array
    {
        $byId = [];
        foreach ($definitions as $definition) {
            $byId[$definition->id] = $definition;
        }

        $aligned = [];
        foreach ($definitions as $definition) {
            $aligned[] = $definition instanceof AliasDefinition && !ContextualSynthetics::isSynthetic($definition->id)
                ? AliasChains::ridden($definition, $byId, $container)
                : $definition;
        }

        return $aligned;
    }
}
