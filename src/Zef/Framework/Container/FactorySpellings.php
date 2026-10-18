<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `factory` shape's spelling grammar: the `'Target::method'` string
 * a factory declaration carries, validated fail-closed.
 *
 * The spelling grammar is judged through the explode idiom rather than
 * a counting function: `count(explode('::', $s)) === 2` pins the
 * "exactly one separator" rule with no string-count call whose mb_*
 * twin could be swapped for it (the counting forms are byte-identical
 * for an ASCII needle -- an immortal mutation zone).
 */
final readonly class FactorySpellings
{
    /**
     * Validates the factory spelling into the verbatim string.
     */
    public static function validated(string $id, mixed $factory): string
    {
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

        return $factory;
    }
}
