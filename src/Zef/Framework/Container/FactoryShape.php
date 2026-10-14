<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `factory` shape's parser (one of the four definition shapes; see
 * {@see DefinitionShapes} for the dispatch).
 *
 * The shape carries a `'Target::method'` spelling and an optional positional
 * argument list. The spelling grammar is judged through the explode idiom
 * rather than a counting function: `count(explode('::', $s)) === 2` pins the
 * "exactly one separator" rule with no string-count call whose mb_* twin
 * could be swapped for it (the counting forms are byte-identical for an
 * ASCII needle -- an immortal mutation zone).
 */
final readonly class FactoryShape
{
    /**
     * @param array<array-key, mixed> $entry
     */
    public static function fromEntry(string $id, array $entry): FactoryDefinition
    {
        $factory = $entry['factory'];
        if (!\is_string($factory) || $factory === '') {
            throw new DefinitionException(
                "Service '{$id}' factory must be a non-empty 'Target::method' string, "
                . get_debug_type($factory) . ' given.',
            );
        }

        if (\count(explode('::', $factory)) !== 2) {
            throw new DefinitionException(
                "Service '{$id}' factory '{$factory}' must carry exactly one '::' separator.",
            );
        }

        [$target, $method] = explode('::', $factory);
        if ($target === '' || $method === '') {
            throw new DefinitionException(
                "Service '{$id}' factory '{$factory}' must name both a target and a method.",
            );
        }

        return new FactoryDefinition($id, $factory, DefinitionArguments::assertValid($id, $entry['arguments'] ?? []));
    }
}
