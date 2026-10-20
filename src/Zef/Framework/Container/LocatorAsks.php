<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The subset locator's construction asks ({@see ContainerLocator}):
 * every identifier a host names for a locator's subset, validated
 * one by one -- non-empty, an identifier the container can answer,
 * and not the boot's own machinery -- and collected into the subset's
 * membership map, repeats collapsed.
 *
 * The validation is the locator's own grammar, its own unit the way
 * the declaration grammar's spelling validations are
 * ({@see ClassSpellings}, {@see FactorySpellings}): the locator keeps
 * the boundary, this class keeps the ask.
 */
final readonly class LocatorAsks
{
    /**
     * Answers the validated membership map for the asked identifiers.
     *
     * A repeated identifier collapses into the one subset member it
     * already is: the subset has no multiplicity, and the first ask's
     * position is the order {@see ContainerLocator::ids()} answers.
     *
     * @return array<string, true> the subset's membership, keyed by identifier
     */
    public static function validated(Container $container, string ...$ids): array
    {
        $subset = [];

        foreach ($ids as $id) {
            if ($id === '') {
                throw LocatorRefusals::identifierEmpty();
            }

            if (str_starts_with($id, '@')) {
                throw LocatorRefusals::identifierMachinery($id);
            }

            if (!$container->has($id)) {
                throw LocatorRefusals::identifierUnknown($id);
            }

            $subset[$id] = true;
        }

        return $subset;
    }
}
