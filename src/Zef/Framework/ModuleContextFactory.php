<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Modules\ModuleConfigRefusals;
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
        $version = $moduleDeclaration->metadata->version;
        $key = ModuleName::configKey($name);
        $schema = $moduleDeclaration->configSchema;

        $block = $config->get($key, []);

        if (!\is_array($block)) {
            ModuleConfigRefusals::blockNotAnArray($key, $name, $block);
        }

        $validated = [];

        foreach ($block as $field => $value) {
            if (!\is_string($field)) {
                ModuleConfigRefusals::blockFieldNotAName($key, $name, $field);
            }

            $validated[$field] = $value;
        }

        if (\is_string($schema)) {
            $engine = ModuleConfigSchema::validated(
                $this->moduleManifestLoader->load($schema, 'module schema'),
                $name,
                $schema,
            );

            ModuleConfigRefusals::asserted($engine, $validated, $name);
        } elseif ($validated !== []) {
            ModuleConfigRefusals::blockWithoutSchema($name, $key);
        }

        return new ModuleContext($name, $version, $validated);
    }
}
