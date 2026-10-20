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
 *
 * The `lazy` key asks the resolution lane to defer the invocation
 * behind a native proxy of the factory method's declared return class
 * ({@see LazyReturns::behindTheDeclaredReturn()} -- the only honest
 * source of the class a lazy factory can name, each unnameable
 * return-type spelling refused with the way out in the message).
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

        $factory = FactorySpellings::validated($id, $entry['factory']);
        $lifetime = ServiceLifetime::fromKey($id, $entry['lifetime'] ?? null) ?? ServiceLifetime::SINGLETON;

        return new FactoryDefinition(
            $id,
            $factory,
            DefinitionArguments::assertValid($id, $entry['arguments'] ?? []),
            $lifetime,
            ServiceTagKey::fromKey($id, $entry['tags'] ?? null) ?? new ServiceTags(),
        )
            ->withMethodCalls(MethodCalls::fromKey($id, null, $entry))
            ->withLazyClass(LazyReturns::behindTheDeclaredReturn($id, $entry, $lifetime, $factory))
        ;
    }
}
