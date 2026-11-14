<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One alias's ride through the planned set (#769's walk): the aligned
 * form of a single alias -- transitively through alias chains, because
 * the chain `a -> b -> c` all ride `c`'s policy whatever the
 * declaration order.
 *
 * The open ids ({@see $visited}) are the walk's cycle arm: a cycle met
 * mid-walk answers the alias unchanged, because the cycle's refusal is
 * the graph validator's own verdict one stage later -- the alignment
 * judges lifetimes only, never the set's own validity. The bookkeeping
 * is read through isset() alone (truthiness-blind), the same
 * isset-only class the `infection.json5` TrueValue ignore documents.
 *
 * The walk continues into an ordinary-alias target through {@see
 * AliasTargets}'s answer (the target fold hands the continuation
 * back), one direction only -- a dependency cycle between the two
 * units would be its own error-level architecture finding.
 */
final readonly class AliasChains
{
    /**
     * The alias rewritten to its target's policy, or the alias itself
     * when the ride changes nothing.
     *
     * @param array<string, ServiceDefinition> $byId
     * @param array<string, true>              $visited the walk's open ids
     */
    public static function ridden(
        AliasDefinition $aliasDefinition,
        array $byId,
        Container $container,
        array $visited = [],
    ): AliasDefinition {
        if (isset($visited[$aliasDefinition->id])) {
            return $aliasDefinition;
        }

        $visited[$aliasDefinition->id] = true;

        $ridden = AliasTargets::ridden($aliasDefinition->target, $byId, $container);

        $lifetime = $ridden instanceof AliasDefinition
            ? self::ridden($ridden, $byId, $container, $visited)->lifetime
            : $ridden;

        return $lifetime === $aliasDefinition->lifetime
            ? $aliasDefinition
            : new AliasDefinition($aliasDefinition->id, $aliasDefinition->target, $lifetime);
    }
}
