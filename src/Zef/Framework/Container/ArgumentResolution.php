<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The resolution half of a definition's argument list (see
 * {@see DefinitionArguments} for the validation half).
 *
 * The reference form is recognised by its grammar shape -- a single-key
 * map whose 'service' key holds a non-empty string identifier, the same
 * single-key grammar {@see ArgumentReference} validates at parse time,
 * so the two lanes answer one entry identically (issue #800's finding 1:
 * the first runtime spelling resolved *any* map that merely carried a
 * 'service' key, disagreeing with the validator and letting a
 * hand-built {@see InstanceDefinition} smuggle a multi-key map past a
 * grammar the declarative lane refuses). The literal escape is the same
 * grammar's own spelling: a single-key `['literal' => $value]` map
 * answers `$value` itself, the one way to hand the resolution a value
 * whose own shape is the reference form. An entry that carries either
 * reserved key in any other shape is refused here too -- the ambiguity
 * is surfaced at the instantiation seam instead of silently resolved,
 * and the identifier is re-narrowed to a string before it reaches
 * {@see Container::get()}.
 */
final readonly class ArgumentResolution
{
    /**
     * @param list<mixed> $arguments
     *
     * @return list<mixed>
     */
    public static function resolve(array $arguments, Container $container): array
    {
        return array_map(
            static fn (mixed $argument): mixed => self::resolvedEntry($argument, $container),
            $arguments,
        );
    }

    /**
     * The resolution of one argument entry: a well-formed reference answers
     * the container's service, a well-formed literal escape answers its own
     * value, a malformed carrier of either reserved key is refused, and
     * anything else is the plain value verbatim.
     */
    private static function resolvedEntry(mixed $argument, Container $container): mixed
    {
        if (\is_array($argument) && \array_key_exists('service', $argument)) {
            if (\count($argument) !== 1) {
                throw new DefinitionException(
                    "An argument entry must not carry 'service' beside other keys: the reference "
                    . "form owns the whole map (['service' => identifier]; a literal map that must "
                    . "keep its own 'service' key escapes through ['literal' => value]).",
                );
            }

            if (!\is_string($argument['service']) || $argument['service'] === '') {
                throw new DefinitionException(
                    'An argument entry reference must name a non-empty service identifier, '
                    . get_debug_type($argument['service']) . ' given.',
                );
            }

            return $container->get($argument['service']);
        }

        if (\is_array($argument) && \array_key_exists('literal', $argument)) {
            if (\count($argument) !== 1) {
                throw new DefinitionException(
                    "An argument entry must not carry 'literal' beside other keys: the literal "
                    . 'escape owns the whole map (single key only).',
                );
            }

            return $argument['literal'];
        }

        return $argument;
    }
}
