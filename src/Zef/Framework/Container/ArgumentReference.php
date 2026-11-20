<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The reference form of one argument entry (the validation half's per-entry
 * grammar; see {@see DefinitionArguments}).
 *
 * An entry is either a plain value (passed through verbatim), a service
 * reference -- a single-key map spelled `['service' => 'identifier']` --
 * or a literal escape: a single-key map spelled `['literal' => $value]`,
 * the one way to hand the resolution a value whose own shape is the
 * reference form (`['literal' => ['service' => 'x']]` answers the array
 * `['service' => 'x']` itself, never the container's answer for `x`).
 * A multi-key map that *contains* a `service` or `literal` key is rejected
 * rather than guessed: both reserved keys own the whole map, so the
 * ambiguity is surfaced instead of silently resolved -- and because the
 * resolution half ({@see ArgumentResolution}) recognises the reference by
 * the same single-key grammar, a hand-built definition that bypassed this
 * validation surfaces the same refusal at its own instantiation seam
 * instead of resolving differently from the declared spelling (issue
 * #800's finding 1: the two lanes disagreed, the loose runtime resolving
 * any map that merely *carried* a `service` key).
 */
final readonly class ArgumentReference
{
    /**
     * The grammar check of one argument entry, fail-closed.
     *
     * @return mixed the entry, verbatim
     */
    public static function assertWellFormed(string $id, mixed $entry): mixed
    {
        if (\is_array($entry) && \array_key_exists('service', $entry)) {
            if (\count($entry) !== 1) {
                throw new DefinitionException(
                    "Service '{$id}' argument reference must be the single key 'service' "
                    . '(extra keys alongside it are rejected; a literal map that must keep its own '
                    . "'service' key escapes through the single-key form ['literal' => value]).",
                );
            }

            if (!\is_string($entry['service']) || $entry['service'] === '') {
                throw new DefinitionException(
                    "Service '{$id}' argument reference must name a non-empty service identifier, "
                    . get_debug_type($entry['service']) . ' given.',
                );
            }
        }

        if (\is_array($entry) && \array_key_exists('literal', $entry) && \count($entry) !== 1) {
            throw new DefinitionException(
                "Service '{$id}' argument literal escape must be the single key 'literal' "
                . '(a map that carries it beside other keys is neither the escape nor a plain value).',
            );
        }

        return $entry;
    }
}
