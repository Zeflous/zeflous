<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `class` shape's parser (one of the four definition shapes; see
 * {@see DefinitionShapes} for the dispatch).
 *
 * The shape carries a class name and an optional positional argument list.
 * The class must be a non-empty string naming an existing class: a
 * definition that survives boot can only fail at instantiation through its
 * arguments' references (which the {@see ServiceDefinitionGraph} has already
 * proven resolvable) -- never through a missing class.
 */
final readonly class InstanceShape
{
    /**
     * @param array<array-key, mixed> $entry
     */
    public static function fromEntry(string $id, array $entry): InstanceDefinition
    {
        $class = $entry['class'];
        if (!\is_string($class) || $class === '') {
            throw new DefinitionException(
                "Service '{$id}' class must be a non-empty class name, " . get_debug_type($class) . ' given.',
            );
        }

        if (!class_exists($class)) {
            throw new DefinitionException("Service '{$id}' class '{$class}' does not exist.");
        }

        return new InstanceDefinition($id, $class, DefinitionArguments::assertValid($id, $entry['arguments'] ?? []));
    }
}
