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
 * The walk answers one lifetime per alias, transitively:
 *
 * - a target **declared in the set** answers its declaration's
 *   lifetime, an alias target its *aligned* lifetime (the chain
 *   `a -> b -> c` all ride `c`'s policy, whatever the declaration
 *   order);
 * - a target the set does not carry but the container does answers
 *   the registration's own policy ({@see Container::lifetime()} -- the
 *   pre-boot programmatic `set()` and the namespace fallback's
 *   covering prefix both answer there, the same totality `has()`
 *   answers with);
 * - a target nobody carries is left alone -- the unknown-target
 *   refusal is the graph validator's own verdict, one stage later, and
 *   a cycle met mid-walk is left alone the same way ({@see
 *   DefinitionCycles} refuses it right after). The alignment judges
 *   lifetimes only, never the set's own validity.
 *
 * The contextual lane's synthetic aliases are *not* this stage's
 * subjects: `@contextual:<consumer>|<dep>` deliberately carries the
 * consumer's lifetime (the rewritten chain must behave exactly the way
 * a direct consumer-to-target reference would -- its capture analysis
 * and its memoisation both see the consumer's policy, {@see
 * ContextualSet}). That is a different contract from transparency, and
 * it is honoured by skipping the prefix the lane owns.
 *
 * The stage sits between the contextual apply and the decoration apply
 * on purpose: a decorated alias's wrapper chain and re-homed base are
 * built from the declaration's lifetime, so the alignment must land
 * first for every layer of the chain to carry the target's policy
 * consistently.
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
        $memo = [];
        foreach ($definitions as $definition) {
            $aligned[] = $definition instanceof AliasDefinition && !self::isContextualSynthetic($definition->id)
                ? self::aligned($definition, $byId, $container, $memo, [])
                : $definition;
        }

        return $aligned;
    }

    /**
     * One alias riding its target's policy, transitively through
     * alias chains, memoised so a shared chain link walks once.
     *
     * @param array<string, ServiceDefinition> $byId
     * @param array<string, AliasDefinition>   $memo
     * @param array<string, true>              $visited the walk's open ids -- a cycle met
     *                                                  mid-walk answers the alias unchanged (the
     *                                                  graph refuses it one stage later)
     */
    private static function aligned(
        AliasDefinition $aliasDefinition,
        array $byId,
        Container $container,
        array &$memo,
        array $visited,
    ): AliasDefinition {
        if (isset($memo[$aliasDefinition->id])) {
            return $memo[$aliasDefinition->id];
        }

        if (isset($visited[$aliasDefinition->id])) {
            return $aliasDefinition;
        }

        $visited[$aliasDefinition->id] = true;

        $serviceLifetime = self::targetLifetime($aliasDefinition->target, $byId, $container, $memo, $visited);

        return $memo[$aliasDefinition->id] = $serviceLifetime === $aliasDefinition->lifetime
            ? $aliasDefinition
            : new AliasDefinition($aliasDefinition->id, $aliasDefinition->target, $serviceLifetime);
    }

    /**
     * The sharing policy one target rides: a declared definition's own,
     * an ordinary alias target's aligned one, and a container-carried
     * leaf's registration -- the single seam the walk's three lanes
     * share.
     *
     * @param array<string, ServiceDefinition> $byId
     * @param array<string, AliasDefinition>   $memo
     * @param array<string, true>              $visited
     */
    private static function targetLifetime(
        string $target,
        array $byId,
        Container $container,
        array &$memo,
        array $visited,
    ): ServiceLifetime {
        $definition = $byId[$target] ?? null;

        if ($definition === null) {
            // A leaf the container carries answers its registration's
            // own policy; a leaf nobody carries answers the default the
            // graph's unknown-target refusal addresses one stage later.
            return $container->lifetime($target) ?? ServiceLifetime::SINGLETON;
        }

        if ($definition instanceof AliasDefinition && !self::isContextualSynthetic($target)) {
            return self::aligned($definition, $byId, $container, $memo, $visited)->lifetime;
        }

        return $definition->lifetime;
    }

    /**
     * Whether the identifier is the contextual lane's own synthetic
     * spelling -- the prefix that lane produces and owns
     * ({@see ContextualSet}).
     */
    private static function isContextualSynthetic(string $id): bool
    {
        return str_starts_with($id, '@contextual:');
    }
}
