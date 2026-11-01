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
            ModuleConfigRefusals::schemaNotAMap($payload, $module, $path);
        }

        $fields = [];

        foreach ($payload as $field => $rule) {
            if (!\is_string($field) || $field === '') {
                ModuleConfigRefusals::schemaFieldNotAName($module, $path, $field);
            }

            $shape = ModuleSchemaRules::shape($rule);

            if ($shape === null) {
                ModuleConfigRefusals::schemaRuleMalformed($module, $path, $field);
            }

            $fields[$field] = $shape;
        }

        return new ConfigSchema($fields);
    }
}
