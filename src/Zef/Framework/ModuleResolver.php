<?php

declare(strict_types=1);

namespace Zef\Framework;

use Throwable;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleEntries;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleInterface;
use Zef\Framework\Modules\ModuleManifest;

/**
 * The discovery fold: one knob entry's spelling resolved into its
 * declaration and its instance.
 *
 * Two spellings arrive ({@see ModuleEntries::classify()}) and both converge
 * on the same answer. The class spelling resolves through the autoloader;
 * the manifest spelling loads the `module.php`, parses it through the
 * manifest grammar, resolves its class, resolves its `config_schema` path
 * against the manifest's own directory (a rooted spelling rides verbatim),
 * and then demands the ONE check that keeps the declarative and the live
 * spellings honest: the class's own `metadata()` must answer the same
 * identity the manifest declares -- a manifest that says one thing while
 * the class answers another is a refusal naming both, because whichever
 * the boot believed, half the record would be lying.
 *
 * Instantiation happens here, once per module: a module class is
 * constructed with no arguments, and a construction that throws is a
 * refusal naming the class and the reason -- discovery never hands the
 * boot a module it could not build.
 */
final readonly class ModuleResolver
{
    public function __construct(
        private ModuleManifestLoader $moduleManifestLoader = new ModuleManifestLoader(),
    ) {
    }

    /**
     * @return array{0: ModuleDeclaration, 1: ModuleInterface}
     *
     * @throws ModuleException when the entry's spelling, its class, its
     *                         manifest or the manifest/class identity
     *                         agreement fails
     */
    public function resolve(string $entry): array
    {
        return match (ModuleEntries::classify($entry)) {
            ModuleEntries::KIND_CLASS => $this->fromClass($entry),
            ModuleEntries::KIND_MANIFEST => $this->fromManifest($entry),
        };
    }

    /**
     * @return array{0: ModuleDeclaration, 1: ModuleInterface}
     */
    private function fromClass(string $entry): array
    {
        if (!class_exists($entry)) {
            throw new ModuleException(
                "The module class '{$entry}' does not exist (is the autoloader "
                . 'aware of it?).',
            );
        }

        $module = $this->instantiate($entry);

        return [new ModuleDeclaration($entry, $module->metadata()), $module];
    }

    /**
     * @return array{0: ModuleDeclaration, 1: ModuleInterface}
     */
    private function fromManifest(string $entry): array
    {
        $payload = $this->moduleManifestLoader->load($entry, 'module manifest');
        $declaration = ModuleManifest::parse($payload);

        if ($declaration->configSchema !== null) {
            $declaration = $declaration->withConfigSchema(
                $this->absoluteSchema($declaration->configSchema, $entry),
            );
        }

        $class = $declaration->class;

        if (!class_exists($class)) {
            throw new ModuleException(
                "The module class '{$class}' (declared by the manifest "
                . "'{$entry}') does not exist (is the autoloader aware of it?).",
            );
        }

        $module = $this->instantiate($class);
        $moduleMetadata = $module->metadata();
        $declared = $declaration->metadata;

        if (
            $moduleMetadata->name !== $declared->name
            || $moduleMetadata->version !== $declared->version
            || $moduleMetadata->requires !== $declared->requires
        ) {
            throw new ModuleException(
                "The module class '{$class}' answers the identity "
                . "'{$moduleMetadata->name}' at '{$moduleMetadata->version}' requiring ["
                . implode(', ', $moduleMetadata->requires) . '] while its manifest '
                . "'{$entry}' declares '{$declared->name}' at "
                . "'{$declared->version}' requiring ["
                . implode(', ', $declared->requires) . ']; one module is one '
                . 'identity.',
            );
        }

        return [$declaration, $module];
    }

    private function instantiate(string $class): ModuleInterface
    {
        if (!is_a($class, ModuleInterface::class, true)) {
            throw new ModuleException(
                "The module class '{$class}' does not implement the module "
                . 'contract (' . ModuleInterface::class . ').',
            );
        }

        try {
            $module = new $class();
        } catch (Throwable $throwable) {
            throw new ModuleException(
                "The module class '{$class}' could not be constructed: "
                . $throwable->getMessage() . '.',
                0,
                $throwable,
            );
        }

        return $module;
    }

    private function absoluteSchema(string $schema, string $manifest): string
    {
        if (str_starts_with($schema, './')) {
            $schema = mb_substr($schema, 2);
        }

        if (str_starts_with($schema, '/')) {
            return $schema;
        }

        return \dirname($manifest) . '/' . $schema;
    }
}
