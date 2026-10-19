<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decorator entry's argument validation: the ordinary reference
 * grammar applied under the knob's own honest label ('decorators.T' --
 * the knob path the entry arrived by), with the reserved inner
 * reference's presence proven on the validated list -- a decorator that
 * never sees the service it decorates is not a decoration but a
 * rebinding, and the refusal says so.
 */
final readonly class DecoratorEntryArguments
{
    /**
     * Validates the entry's arguments and proves the inner reference's
     * presence.
     *
     * @param array<array-key, mixed> $entry
     *
     * @return list<mixed>
     */
    public static function validated(string $target, array $entry): array
    {
        $validated = DefinitionArguments::assertValid('decorators.' . $target, $entry['arguments'] ?? []);

        if (!DecoratorInner::carried($validated)) {
            throw DecoratorRefusals::missingInner($target);
        }

        return $validated;
    }
}
