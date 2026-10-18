<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `value` shape's parser (one of the four definition shapes; see
 * {@see DefinitionShapes} for the dispatch).
 *
 * The shape is a raw scalar/array parameter registered as a service --
 * injectable configuration ("parameter global" in the roadmap's
 * registration area): strings, numbers, booleans, null and arrays travel
 * through the container like any other service, so factories and class
 * arguments can reference them uniformly. It carries no arguments: a
 * definition that spells a value while also carrying an `arguments` key is
 * rejected rather than silently ignoring the extra key.
 */
final readonly class ValueShape
{
    /**
     * @param array<array-key, mixed> $entry
     */
    public static function fromEntry(string $id, array $entry): ValueDefinition
    {
        if (\array_key_exists('arguments', $entry)) {
            throw new DefinitionException(
                "Service '{$id}' carries arguments on a 'value' definition "
                . '(arguments belong to class and factory definitions only).',
            );
        }

        if (\array_key_exists('autowire', $entry)) {
            throw new DefinitionException(
                "Service '{$id}' carries the 'autowire' key on a 'value' definition "
                . '(the whole-constructor planning request belongs to the class shape only).',
            );
        }

        if (\array_key_exists('lifetime', $entry)) {
            throw new DefinitionException(
                "Service '{$id}' carries a 'lifetime' on a 'value' definition "
                . '(a value is a constant: it is born once and never built again).',
            );
        }

        if (\array_key_exists('tags', $entry)) {
            throw new DefinitionException(
                "Service '{$id}' carries 'tags' on a 'value' definition "
                . '(a value is a constant: it is not a service to group).',
            );
        }

        if (\array_key_exists('calls', $entry)) {
            throw new DefinitionException(
                "Service '{$id}' carries 'calls' on a 'value' definition "
                . '(a value is a constant: there is no instance to call methods on).',
            );
        }

        return new ValueDefinition($id, $entry['value']);
    }
}
