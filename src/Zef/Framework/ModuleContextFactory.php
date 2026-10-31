<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Modules\ModuleConfigSchema;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleName;

/**
 * The boot phase's context assembler: the configuration block a module's
 * own name derives, asserted against the schema the module declared, and
 * handed over as the readonly {@see ModuleContext} the `boot()` contract
 * receives.
 *
 * The namespacing rule the roadmap spells lives here as its two refusals:
 * the block lives at the dotted module name (`zef/payment` reads
 * `zef.payment`) and nowhere else, so two modules' blocks cannot collide;
 * and a block that arrives without a declared schema is refused -- the
 * roadmap's "every module provides a schema" as an enforceable posture, not
 * a wish. A declared schema without a block is the opposite arm: the schema
 * asserts the empty block, so a module whose configuration demands a
 * required field refuses with the field named, the module named with it.
 *
 * The schema's own verdicts arrive as the configuration layer's typed
 * refusal and leave wrapped as the module lane's, the module named first
 * and the configuration refusal chained -- the error family's posture, the
 * boundary renders whichever tier it meets.
 */
final readonly class ModuleContextFactory
{
    public function __construct(
        private ModuleManifestLoader $moduleManifestLoader = new ModuleManifestLoader(),
    ) {
    }

    /**
     * @throws ModuleException when the block is not an array or not a map of
     *                         string fields, arrives without a declared
     *                         schema, or fails its declared schema
     */
    public function context(ModuleDeclaration $moduleDeclaration, Config $config): ModuleContext
    {
        $name = $moduleDeclaration->metadata->name;
        $key = new ModuleName($name)->toConfigKey();

        $block = [];

        if ($config->has($key)) {
            $block = $config->get($key);

            if (!\is_array($block)) {
                throw new ModuleException(
                    "The configuration block '{$key}' of the module '{$name}' "
                    . 'must be an array; ' . get_debug_type($block) . ' arrived.',
                );
            }
        }

        $validated = $this->validatedMap($block, $key, $name);

        if ($moduleDeclaration->configSchema === null) {
            if ($validated !== []) {
                throw new ModuleException(
                    "The module '{$name}' carries the configuration block "
                    . "'{$key}' but declares no config_schema; a block arrives "
                    . 'only with the schema that validates it.',
                );
            }

            return new ModuleContext($name, $moduleDeclaration->metadata->version, $validated);
        }

        $configSchema = ModuleConfigSchema::validated(
            $this->moduleManifestLoader->load($moduleDeclaration->configSchema, 'module schema'),
            $name,
            $moduleDeclaration->configSchema,
        );

        try {
            $configSchema->assert($validated);
        } catch (ConfigException $configException) {
            throw new ModuleException(
                "The module '{$name}' failed its configuration schema: "
                . $configException->getMessage(),
                0,
                $configException,
            );
        }

        return new ModuleContext($name, $moduleDeclaration->metadata->version, $validated);
    }

    /**
     * Rebuilds the block cell-by-cell as it validates -- the payload the
     * context hands over is a record the analyser can check, not a promise
     * it must trust: every field is proven a string key here, so the
     * declared schema asserts an already-honest map.
     *
     * @param array<array-key, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function validatedMap(array $block, string $key, string $name): array
    {
        $validated = [];

        foreach ($block as $field => $value) {
            if (!\is_string($field)) {
                throw new ModuleException(
                    "The configuration block '{$key}' of the module '{$name}' "
                    . 'must be a map of string fields; an ' . get_debug_type($field)
                    . ' field arrived.',
                );
            }

            $validated[$field] = $value;
        }

        return $validated;
    }
}
