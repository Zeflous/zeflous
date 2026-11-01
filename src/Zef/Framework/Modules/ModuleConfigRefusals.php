<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

use Throwable;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\ConfigSchema;

/**
 * The module configuration's refusals: the schema file's three arms and
 * the block's three arms -- one fold, so the configuration side's refusal
 * is spelled once and every caller carries the same sentence. The kind
 * vocabulary lives beside the refusal that spells it.
 */
final class ModuleConfigRefusals
{
    /**
     * The module configuration schema's own closed kind vocabulary -- the
     * schema engine's four value kinds, owned here so the checks and the
     * refusal that spells them can never disagree.
     */
    public const array SCHEMA_KINDS = ['string', 'string-map', 'strings', 'values'];

    /**
     * @throws ModuleException always
     */
    public static function schemaNotAMap(mixed $payload, string $module, string $path): never
    {
        throw new ModuleException(
            "The configuration schema of the module '{$module}' at "
            . "'{$path}' must be a non-empty field-rule map; "
            . (\is_array($payload) ? 'an empty map' : get_debug_type($payload))
            . ' arrived.',
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function schemaFieldNotAName(string $module, string $path, mixed $field): never
    {
        throw new ModuleException(
            "The configuration schema of the module '{$module}' at "
            . "'{$path}' carries a field whose name is not a non-empty "
            . 'string: ' . (\is_string($field) ? "''" : get_debug_type($field))
            . ' arrived.',
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function schemaRuleMalformed(string $module, string $path, string $field): never
    {
        $spelled = implode("', '", self::SCHEMA_KINDS);

        throw new ModuleException(
            "The configuration schema of the module '{$module}' at "
            . "'{$path}' carries the malformed rule for the field "
            . "'{$field}': a rule is exactly ['required' => bool, 'kind' "
            . "=> one of '{$spelled}'].",
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function blockFieldNotAName(string $key, string $name, mixed $field): never
    {
        throw new ModuleException(
            "The configuration block '{$key}' of the module '{$name}' "
            . 'must be a map of string fields; an ' . get_debug_type($field)
            . ' field arrived.',
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function blockNotAnArray(string $key, string $name, mixed $block): never
    {
        throw new ModuleException(
            "The configuration block '{$key}' of the module '{$name}' "
            . 'must be an array; ' . get_debug_type($block) . ' arrived.',
        );
    }

    /**
     * @throws ModuleException always
     */
    public static function blockWithoutSchema(string $name, string $key): never
    {
        throw new ModuleException(
            "The module '{$name}' carries the configuration block "
            . "'{$key}' but declares no config_schema; a block arrives "
            . 'only with the schema that validates it.',
        );
    }

    /**
     * Asserts the block against the built schema engine -- the wrapper
     * that keeps the configuration refusal's tier under the module lane's
     * own, the module named first.
     *
     * @param array<string, mixed> $block
     *
     * @throws ModuleException when the block fails its schema
     */
    public static function asserted(ConfigSchema $configSchema, array $block, string $name): void
    {
        try {
            $configSchema->assert($block);
        } catch (ConfigException $configException) {
            self::schemaRefused($name, $configException);
        }
    }

    /**
     * @throws ModuleException always
     */
    public static function schemaRefused(string $name, Throwable $throwable): never
    {
        throw new ModuleException(
            "The module '{$name}' failed its configuration schema: "
            . $throwable->getMessage(),
            0,
            $throwable,
        );
    }
}
