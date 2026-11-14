<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The sharing policy one alias target rides (#769's read): the single
 * seam the walk's three target lanes share.
 *
 * - a target **declared in the set** answers its declaration's
 *   lifetime, or -- when the target is itself an ordinary alias --
 *   the target definition handed back for the walk to continue
 *   through ({@see AliasChains} owns the continuation, one direction
 *   only);
 * - a contextual lane's synthetic alias target answers its declared
 *   (consumer) lifetime directly -- the walk honours the target it
 *   finds, it does not re-align it: that is a different contract from
 *   transparency, honoured rather than overridden ({@see
 *   ContextualSynthetics});
 * - a target nobody declared answers the container's own read for it
 *   ({@see Container::lifetime()} -- the pre-boot programmatic `set()`
 *   and the namespace fallback's covering prefix both answer there),
 *   defaulting to the singleton the graph's unknown-target refusal
 *   addresses one stage later.
 */
final readonly class AliasTargets
{
    /**
     * The policy the target rides, or the ordinary-alias target itself
     * when the walk must continue through it.
     *
     * @param array<string, ServiceDefinition> $byId
     */
    public static function ridden(string $target, array $byId, Container $container): AliasDefinition|ServiceLifetime
    {
        $definition = $byId[$target] ?? null;

        if ($definition === null) {
            return $container->lifetime($target) ?? ServiceLifetime::SINGLETON;
        }

        if ($definition instanceof AliasDefinition && !ContextualSynthetics::isSynthetic($target)) {
            return $definition;
        }

        return $definition->lifetime;
    }
}
