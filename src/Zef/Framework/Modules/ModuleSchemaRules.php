<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The module configuration rule's own shape: the judgment that reads one
 * schema-file entry as exactly the `required`/`kind` pair over the closed
 * kind vocabulary -- beside the refusal that names the malformed rule and
 * the vocabulary that refusal spells.
 */
final class ModuleSchemaRules
{
    private const array KINDS = ModuleConfigRefusals::SCHEMA_KINDS;

    /**
     * The rule the schema engine reads, or null when the entry is not
     * exactly the pair -- a stray key, a missing arm and a typo'd kind are
     * all the same null.
     *
     * @return null|array{required: bool, kind: 'string'|'string-map'|'strings'|'values'}
     */
    public static function shape(mixed $rule): ?array
    {
        if (!\is_array($rule) || \count($rule) !== 2) {
            return null;
        }

        $required = $rule['required'] ?? null;
        $kind = $rule['kind'] ?? null;

        if (\is_bool($required) && \is_string($kind) && \in_array($kind, self::KINDS, true)) {
            // @var array{required: bool, kind: 'string'|'string-map'|'strings'|'values'}
            return ['required' => $required, 'kind' => $kind];
        }

        return null;
    }
}
