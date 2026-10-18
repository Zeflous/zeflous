<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `factory` shape's parser (one of the four definition shapes; see
 * {@see DefinitionShapes} for the dispatch).
 *
 * The shape carries a `'Target::method'` spelling
 * ({@see FactorySpellings} for the spelling grammar) and an optional
 * positional argument list. The optional `calls` list parses without
 * the method-existence proof: the factory's product type is unknown at
 * boot, so the proof waits for the first resolution.
 */
final readonly class FactoryShape
{
    /**
     * @param array<array-key, mixed> $entry
     */
    public static function fromEntry(string $id, array $entry): FactoryDefinition
    {
        if (\array_key_exists('autowire', $entry)) {
            throw new DefinitionException(
                "Service '{$id}' carries the 'autowire' key on a 'factory' definition "
                . '(the whole-constructor planning request belongs to the class shape only).',
            );
        }

        return new FactoryDefinition(
            $id,
            FactorySpellings::validated($id, $entry['factory']),
            DefinitionArguments::assertValid($id, $entry['arguments'] ?? []),
            ServiceLifetime::fromKey($id, $entry['lifetime'] ?? null) ?? ServiceLifetime::SINGLETON,
            ServiceTagKey::fromKey($id, $entry['tags'] ?? null) ?? new ServiceTags(),
        )->withMethodCalls(MethodCalls::fromKey($id, null, $entry));
    }
}
