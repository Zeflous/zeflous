<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

use Zef\Framework\Config\Schema\ConfigSchema;

/**
 * The module configuration schema: the field-rule map a manifest's
 * `config_schema` file answers, validated into the configuration layer's
 * own {@see ConfigSchema}.
 *
 * The schema file is module-authored, so its shape is validated fail-closed
 * before it becomes a schema engine: every field is a non-empty string
 * naming a rule that is exactly the `required`/`kind` pair the engine
 * reads, with the kind from the engine's own closed vocabulary. A rule the
 * engine cannot read -- a typo'd kind, a missing `required`, a stray key --
 * is a refusal naming the module and the field, never a rule silently
 * ignored; a schema that validated nothing would make the block's
 * validation a ceremony instead of a guard.
 */
final class ModuleConfigSchema
{
    private const array KINDS = ['string', 'string-map', 'strings', 'values'];

    /**
     * Validates the raw schema-file payload and answers the schema engine
     * built from it.
     *
     * @throws ModuleException when the payload is not a non-empty field-rule
     *                         map whose every rule is the exact shape the
     *                         schema engine reads
     */
    public static function validated(mixed $payload, string $module, string $path): ConfigSchema
    {
        if (!\is_array($payload) || $payload === []) {
            throw new ModuleException(
                "The configuration schema of the module '{$module}' at "
                . "'{$path}' must be a non-empty field-rule map; "
                . (\is_array($payload) ? 'an empty map' : get_debug_type($payload))
                . ' arrived.',
            );
        }

        $fields = [];

        foreach ($payload as $field => $rule) {
            [$name, $shape] = self::validatedRule($module, $path, $field, $rule);

            $fields[$name] = $shape;
        }

        return new ConfigSchema($fields);
    }

    /**
     * Validates one field-rule pair and answers it back as the record the
     * schema engine reads -- the map the engine is built from is a record
     * the analyser can check, not a promise it must trust.
     *
     * @return array{non-empty-string, array{required: bool, kind: 'string'|'string-map'|'strings'|'values'}}
     */
    private static function validatedRule(
        string $module,
        string $path,
        mixed $field,
        mixed $rule,
    ): array {
        if (!\is_string($field) || $field === '') {
            throw new ModuleException(
                "The configuration schema of the module '{$module}' at "
                . "'{$path}' carries a field whose name is not a non-empty "
                . 'string: ' . (\is_string($field) ? "''" : get_debug_type($field))
                . ' arrived.',
            );
        }

        if (
            !\is_array($rule)
            || \count($rule) !== 2
            || !\array_key_exists('required', $rule)
            || !\array_key_exists('kind', $rule)
        ) {
            $spelled = implode("', '", self::KINDS);

            throw new ModuleException(
                "The configuration schema of the module '{$module}' at "
                . "'{$path}' carries the malformed rule for the field "
                . "'{$field}': a rule is exactly ['required' => bool, 'kind' "
                . "=> one of '{$spelled}'].",
            );
        }

        $required = $rule['required'];
        $kind = $rule['kind'];

        if (!\is_bool($required) || !\is_string($kind) || !\in_array($kind, self::KINDS, true)) {
            $spelled = implode("', '", self::KINDS);

            throw new ModuleException(
                "The configuration schema of the module '{$module}' at "
                . "'{$path}' carries the malformed rule for the field "
                . "'{$field}': a rule is exactly ['required' => bool, 'kind' "
                . "=> one of '{$spelled}'].",
            );
        }

        return [$field, ['required' => $required, 'kind' => $kind]];
    }
}
