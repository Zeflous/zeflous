<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The validation half of a declarative definition's argument list (see
 * {@see ArgumentResolution} for the resolution half).
 *
 * Arguments are a positional list. An entry is either a plain value (passed
 * through verbatim at resolution time) or a service reference -- a
 * single-key map spelled `['service' => 'identifier']`, resolved through
 * the container at instantiation time. A multi-key map that *contains* a
 * `service` key is rejected rather than guessed: the reference form owns
 * the whole map, so the ambiguity is surfaced instead of silently resolved.
 */
final readonly class DefinitionArguments
{
    /**
     * Validates a raw `arguments` value and returns it as the positional
     * list the definition carries.
     *
     * @return list<mixed>
     */
    public static function assertValid(string $id, mixed $arguments): array
    {
        if (!\is_array($arguments)) {
            throw new DefinitionException(
                "Service '{$id}' arguments must be a list, " . get_debug_type($arguments) . ' given.',
            );
        }

        if (!array_is_list($arguments)) {
            throw new DefinitionException(
                "Service '{$id}' arguments must be a positional list (string keys are rejected).",
            );
        }

        return array_map(
            static fn (mixed $entry): mixed => ArgumentReference::assertWellFormed($id, $entry),
            $arguments,
        );
    }

    /**
     * Resolves the validated argument list: references become the
     * container's answers, plain values pass through verbatim.
     *
     * @param list<mixed> $arguments
     *
     * @return list<mixed>
     */
    public static function resolve(array $arguments, Container $container): array
    {
        return ArgumentResolution::resolve($arguments, $container);
    }
}
