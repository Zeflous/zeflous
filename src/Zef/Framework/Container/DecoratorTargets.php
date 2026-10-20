<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration knob's target entries ({@see DecoratorMap} for the knob
 * itself): the map of targets to their decorator chains, validated
 * entry by entry, fail-closed, under the lane's budget (the
 * {@see ContextualConsumers} posture -- the walk is its own unit so the
 * knob stays the parsed form it answers).
 *
 * A target key must be a non-empty string; its chain is parsed by
 * {@see DecoratorEntries}. PHP's own unique-key contract makes a doubled
 * target impossible from the grammar -- the apply-side guards remain the
 * documented defense against hand-built misuse.
 */
final readonly class DecoratorTargets
{
    /** The hard bound on decorators declared across every target. */
    public const int BUDGET = 128;

    /**
     * Validates the knob's target entries into their parsed chains,
     * holding the decoration budget across the whole map.
     *
     * @param array<array-key, mixed> $source
     *
     * @return array<string, list<DecoratorEntry>>
     */
    public static function decorations(array $source): array
    {
        $decorations = [];
        $total = 0;
        foreach (array_keys($source) as $target) {
            if (!\is_string($target) || $target === '') {
                throw DecoratorRefusals::targetNotAnIdentifier($target);
            }

            $entries = DecoratorEntries::parse($target, $source[$target]);
            $total += \count($entries);
            if ($total > self::BUDGET) {
                throw DecoratorRefusals::budgetExceeded(self::BUDGET);
            }

            $decorations[$target] = $entries;
        }

        return $decorations;
    }
}
