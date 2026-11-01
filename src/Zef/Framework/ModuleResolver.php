<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Modules\ModuleBootRefusals;
use Zef\Framework\Modules\ModuleConstructions;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleEntries;
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
     * @throws Modules\ModuleException when the entry's spelling, its class, its
     *                                 manifest or the manifest/class identity
     *                                 agreement fails
     */
    public function resolve(string $entry): array
    {
        if (ModuleEntries::classify($entry) === ModuleEntries::KIND_MANIFEST) {
            return $this->fromManifest($entry);
        }

        if (!class_exists($entry)) {
            throw ModuleBootRefusals::classMissing($entry);
        }

        $module = ModuleConstructions::answered($entry);

        return [new ModuleDeclaration($entry, $module->metadata()), $module];
    }

    /**
     * @return array{0: ModuleDeclaration, 1: ModuleInterface}
     */
    private function fromManifest(string $entry): array
    {
        $payload = $this->moduleManifestLoader->load($entry, 'module manifest');
        $moduleDeclaration = ModuleManifest::parsed($payload, $entry);
        $class = $moduleDeclaration->class;

        if (!class_exists($class)) {
            throw ModuleBootRefusals::manifestClassMissing($class, $entry);
        }

        $module = ModuleConstructions::answered($class);

        ModuleBootRefusals::assertedIdentityAgreement(
            $module->metadata(),
            $moduleDeclaration->metadata,
            $entry,
        );

        return [$moduleDeclaration, $module];
    }
}
