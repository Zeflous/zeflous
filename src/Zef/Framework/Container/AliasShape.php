<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `alias` shape's parser (one of the four definition shapes; see
 * {@see DefinitionShapes} for the dispatch).
 *
 * The shape is a transparent pointer to another service identifier --
 * interface-to-implementation mapping is the primary use -- and carries no
 * arguments: a definition that spells an alias while also carrying an
 * `arguments` key is rejected rather than silently ignoring the extra key.
 */
final readonly class AliasShape
{
    /**
     * @param array<array-key, mixed> $entry
     */
    public static function fromEntry(string $id, array $entry): AliasDefinition
    {
        $target = $entry['alias'];
        if (!\is_string($target) || $target === '') {
            throw new DefinitionException(
                "Service '{$id}' alias must be a non-empty service identifier, "
                . get_debug_type($target) . ' given.',
            );
        }

        if (\array_key_exists('arguments', $entry)) {
            throw new DefinitionException(
                "Service '{$id}' carries arguments on a 'alias' definition "
                . '(arguments belong to class and factory definitions only).',
            );
        }

        return new AliasDefinition($id, $target);
    }
}
