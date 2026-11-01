<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

use Zef\Framework\Config\Schema\ConfigSchema;

/**
 * The module manifest grammar: the declarative `module.php` payload the
 * roadmap's discovery sketch spells, validated by the configuration
 * layer's own schema engine.
 *
 * The engine reuses {@see ConfigSchema} exactly as that class's docblock
 * anticipated -- a manifest is a configuration entry, so its validation
 * needs no engine of its own: the five fields (name, version, requires,
 * class, config_schema) ride the field-rule map, and an unknown key or a
 * wrong kind is a violation the schema renders, not a silent skip. The
 * kinds the schema answers are then refined by the engine's own grammars:
 * the name and version through {@see ModuleMetadata}, the class through
 * {@see ModuleDeclaration}'s qualified-spelling refusal, the optional
 * `config_schema` kept raw for the discovery fold -- only the fold that
 * read the manifest knows the directory a relative schema path resolves
 * against.
 *
 * A manifest carries no behaviour: it declares which class implements the
 * contract and the identity the boot orders by, so discovery stays free
 * of instantiation until the resolution fold runs.
 */
final readonly class ModuleManifest
{
    private const string FIELD_NAME = 'name';

    private const string FIELD_VERSION = 'version';

    private const string FIELD_REQUIRES = 'requires';

    private const string FIELD_CLASS = 'class';

    private const string FIELD_SCHEMA = 'config_schema';

    /**
     * Parses and validates a manifest payload into its declaration. The
     * declaration arrives carrying the manifest's raw `config_schema`
     * spelling -- only the discovery fold that read the manifest knows the
     * directory a relative spelling resolves against, so it rewrites the
     * path to its absolute form through
     * {@see ModuleDeclaration::withConfigSchema()}.
     *
     * @throws ModuleException when the payload is not an array, fails the
     *                         field schema, or carries a malformed identity
     */
    public static function parse(mixed $payload): ModuleDeclaration
    {
        if (!\is_array($payload)) {
            throw new ModuleException(
                'The module manifest must return an array; '
                . get_debug_type($payload) . ' arrived.',
            );
        }

        $violations = self::schema()->validate($payload);

        if ($violations !== []) {
            throw new ModuleException(
                'The module manifest carries violations: ' . implode('; ', $violations) . '.',
            );
        }

        /**
         * @var array{name: string, version: string, class: string,
         *     requires?: list<string>, config_schema?: string} $payload
         */
        $moduleDeclaration = new ModuleDeclaration(
            $payload[self::FIELD_CLASS],
            new ModuleMetadata(
                $payload[self::FIELD_NAME],
                $payload[self::FIELD_VERSION],
                $payload[self::FIELD_REQUIRES] ?? [],
            ),
        );

        if (isset($payload[self::FIELD_SCHEMA])) {
            return $moduleDeclaration->withConfigSchema($payload[self::FIELD_SCHEMA]);
        }

        return $moduleDeclaration;
    }

    /**
     * Parses the manifest the given file carried and resolves its
     * `config_schema` path against that file's own directory -- the one
     * spelling that knows where a relative path is relative to. A rooted
     * spelling rides verbatim.
     */
    public static function parsed(mixed $payload, string $manifest): ModuleDeclaration
    {
        $moduleDeclaration = self::parse($payload);
        $schema = $moduleDeclaration->configSchema;

        if ($schema === null) {
            return $moduleDeclaration;
        }

        if (str_starts_with($schema, './')) {
            $schema = mb_substr($schema, 2);
        }

        if (str_starts_with($schema, '/')) {
            return $moduleDeclaration->withConfigSchema($schema);
        }

        return $moduleDeclaration->withConfigSchema(\dirname($manifest) . '/' . $schema);
    }

    private static function schema(): ConfigSchema
    {
        return new ConfigSchema([
            self::FIELD_NAME => ['required' => true, 'kind' => 'string'],
            self::FIELD_VERSION => ['required' => true, 'kind' => 'string'],
            self::FIELD_REQUIRES => ['required' => false, 'kind' => 'strings'],
            self::FIELD_CLASS => ['required' => true, 'kind' => 'string'],
            self::FIELD_SCHEMA => ['required' => false, 'kind' => 'string'],
        ]);
    }
}
