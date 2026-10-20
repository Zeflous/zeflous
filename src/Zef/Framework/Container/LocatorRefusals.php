<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The subset locator's refusal messages -- every way a locator's
 * construction or resolution can fail, each with the way out in the
 * message.
 *
 * Kept its own archeology-sized unit the way the lane's other
 * refusal vocabularies are ({@see LazyRefusals},
 * {@see NamespaceRefusals}): the locator keeps the verdicts, this
 * class keeps the words. The resolution-side refusal rides the
 * PSR-11 miss family ({@see NotFoundException}) -- the subset's own
 * boundary is still a "cannot be located" answer.
 */
final readonly class LocatorRefusals
{
    public static function identifierEmpty(): NotFoundException
    {
        return new NotFoundException(
            'A locator identifier must be a non-empty service identifier.',
        );
    }

    public static function identifierMachinery(string $id): NotFoundException
    {
        return new NotFoundException(
            "The identifier '{$id}' is the boot's own machinery and not a host service "
            . '(a locator hands over services, not the machinery that builds them).',
        );
    }

    public static function identifierUnknown(string $id): NotFoundException
    {
        return new NotFoundException(
            "The identifier '{$id}' is not carried by the container "
            . "(a locator's subset names identifiers the container can already answer).",
        );
    }

    public static function outsideTheSubset(string $id): NotFoundException
    {
        return new NotFoundException(
            "Service \"{$id}\" is not in this locator's subset "
            . '(the subset is fixed at construction -- ask the container, or build the locator with it).',
        );
    }
}
