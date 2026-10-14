<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The reference form of one argument entry (the validation half's per-entry
 * grammar; see {@see DefinitionArguments}).
 *
 * An entry is either a plain value (passed through verbatim) or a service
 * reference -- a single-key map spelled `['service' => 'identifier']`. A
 * multi-key map that *contains* a `service` key is rejected rather than
 * guessed: the reference form owns the whole map, so the ambiguity is
 * surfaced instead of silently resolved.
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
                    . '(extra keys alongside it are rejected).',
                );
            }

            if (!\is_string($entry['service']) || $entry['service'] === '') {
                throw new DefinitionException(
                    "Service '{$id}' argument reference must name a non-empty service identifier, "
                    . get_debug_type($entry['service']) . ' given.',
                );
            }
        }

        return $entry;
    }
}
