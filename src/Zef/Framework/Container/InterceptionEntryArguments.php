<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception entry's argument validation: the ordinary reference
 * grammar applied under the entry's own honest label (the knob path
 * the entry arrived by -- 'interceptors.0', 'listeners.resolving.1')
 * through the definition grammar's own validator, so the two knobs'
 * argument surface and the definition surface stay one grammar
 * ({@see DefinitionArguments}).
 *
 * No reserved reference applies here: the decorator entry's `@inner`
 * discipline is that lane's own; an interception entry's arguments are
 * the builder's plain inputs.
 */
final readonly class InterceptionEntryArguments
{
    /**
     * Validates the entry's arguments under the entry's own label.
     *
     * @param array<array-key, mixed> $entry
     *
     * @return list<mixed>
     */
    public static function validated(string $label, array $entry): array
    {
        return DefinitionArguments::assertValid($label, $entry['arguments'] ?? []);
    }
}
