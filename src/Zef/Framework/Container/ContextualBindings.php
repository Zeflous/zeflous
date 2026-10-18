<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The contextual-binding lane's boot apply (ROADMAP, "CONTAINER" area 4):
 * the walk over the knob's parsed bindings, each one applied to the
 * planned definition set by {@see ContextualBinding} between the
 * autoconfiguration merge and the graph validation.
 *
 * The mechanism is the reference container's own ported insight, kept
 * whole: the consumer's dependency reference is rewritten to flow
 * through the binding's synthetic alias, so every existing guarantee of
 * the boot graph holds unchanged for the rewritten set.
 */
final readonly class ContextualBindings
{
    /**
     * Applies every parsed binding to the planned definition set and
     * answers the rewritten set (the synthetic aliases appended).
     *
     * @param list<ServiceDefinition> $definitions
     *
     * @return list<ServiceDefinition>
     */
    public static function apply(array $definitions, ContextualMap $contextualMap): array
    {
        $applied = $definitions;
        foreach ($contextualMap->bindings() as $consumer => $deps) {
            foreach ($deps as $dep => $target) {
                $applied = ContextualBinding::applied($applied, $consumer, $dep, $target);
            }
        }

        return $applied;
    }
}
