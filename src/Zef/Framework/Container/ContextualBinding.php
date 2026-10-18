<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One contextual binding's application to a planned definition set (see
 * {@see ContextualBindings} for the loop that walks the knob).
 *
 * A binding answers the question "when consumer C resolves dependency D,
 * give it T instead" through the reference container's own ported
 * insight: the consumer's dependency reference is rewritten to flow
 * through the synthetic identifier `@contextual:<consumer>|<dep>`,
 * itself an alias to the target -- so the rewritten set's reference
 * graph contains real edges, and every existing guarantee holds
 * unchanged: a missing target fails the graph's unknown-service
 * refusal, cycles through the binding are walked by the cycle
 * discipline, and the lifetime captures see the chain exactly the way a
 * direct consumer-to-target reference would (the synthetic alias
 * carries the consumer's lifetime for precisely that reason).
 *
 * The application refuses honestly: an unregistered consumer, a
 * dependency the consumer's arguments and calls never declare, and an
 * unplanned definition (the autowire pass belongs ahead of this apply).
 */
final readonly class ContextualBinding
{
    /**
     * Applies one binding: guards, then the set surgery.
     *
     * @param list<ServiceDefinition> $definitions
     *
     * @return list<ServiceDefinition>
     */
    public static function applied(array $definitions, string $consumer, string $dep, string $target): array
    {
        [$index, $definition] = ContextualConsumer::effective($definitions, $consumer);

        if ($definition instanceof UnplannedInstanceDefinition) {
            throw ContextualRefusals::unplanned($consumer);
        }

        if (!ContextualDeclarations::declares($definition, $dep)) {
            throw ContextualRefusals::dependencyNotDeclared($consumer, $dep);
        }

        return ContextualSet::withBinding($definitions, $index, $definition, $dep, $target);
    }
}
