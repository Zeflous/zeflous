<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The name-keyed `arguments` form's parser -- the autowiring lane's
 * hybrid declaration grammar (see {@see InstanceArguments} for the
 * form-dispatch).
 *
 * Every key a non-empty parameter name, every entry the same
 * value-or-reference grammar the positional list carries ({@see
 * ArgumentReference}): the named form names the constructor's own
 * parameters, so the compile pass can splice the declaration's
 * spellings over the planned gaps.
 */
final readonly class NamedArguments
{
    /**
     * Validates the name-keyed `arguments` map, fail-closed.
     *
     * @return array<string, mixed>
     */
    public static function assert(string $id, mixed $arguments): array
    {
        if (!\is_array($arguments)) {
            throw new DefinitionException(
                "Service '{$id}' arguments must be a map, " . get_debug_type($arguments) . ' given.',
            );
        }

        $named = [];
        $names = array_keys($arguments);

        foreach ($names as $name) {
            if (!\is_string($name) || $name === '') {
                throw new DefinitionException(
                    "Service '{$id}' name-keyed arguments must carry non-empty parameter names, "
                    . get_debug_type($name) . ' given.',
                );
            }

            $named += [$name => ArgumentReference::assertWellFormed($id, $arguments[$name])];
        }

        return $named;
    }
}
