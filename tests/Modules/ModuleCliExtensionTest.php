<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ModuleCliEntries;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleManifest;
use Zef\Test\Modules\Fixtures\CliModule;

/**
 * The CLI extension point's declaration surface: the manifest's `cli`
 * path field -- parsed like the routes and events paths, resolved
 * against the manifest's own directory by the discovery fold -- and
 * the entry fold over the staged fixture's commands file.
 *
 * @internal
 */
#[CoversClass(ModuleManifest::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleCliEntries::class)]
#[CoversClass(ModuleException::class)]
final class ModuleCliExtensionTest extends TestCase
{
    private const string FIXTURE_MANIFESTS = __DIR__ . '/fixtures';

    public function testTheManifestCarriesTheCliPathResolvedAgainstItsOwnDirectory(): void
    {
        $declaration = ModuleManifest::parsed(
            require self::FIXTURE_MANIFESTS . '/with-cli/module.php',
            self::FIXTURE_MANIFESTS . '/with-cli/module.php',
        );

        self::assertSame(
            self::FIXTURE_MANIFESTS . '/with-cli/commands.php',
            $declaration->cli,
        );
    }

    public function testAManifestWithoutTheCliFieldAnswersNull(): void
    {
        $declaration = ModuleManifest::parse([
            'name' => 'test/shop',
            'version' => '1.0.0',
            'class' => CliModule::class,
        ]);

        self::assertNull($declaration->cli);
    }

    public function testARootedCliSpellingRidesVerbatim(): void
    {
        $declaration = ModuleManifest::parsed(
            [
                'name' => 'test/shop',
                'version' => '1.0.0',
                'class' => CliModule::class,
                'cli' => '/etc/shop/commands.php',
            ],
            self::FIXTURE_MANIFESTS . '/with-cli/module.php',
        );

        self::assertSame('/etc/shop/commands.php', $declaration->cli);
    }

    public function testACliFieldOfTheWrongKindRefusesTheManifest(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('carries violations');

        ModuleManifest::parse([
            'name' => 'test/shop',
            'version' => '1.0.0',
            'class' => CliModule::class,
            'cli' => ['not' => 'a path'],
        ]);
    }

    public function testTheEntryFoldAnswersTheStagedFixtureDeclarations(): void
    {
        $entries = new ModuleCliEntries()->loaded(
            'test/shop',
            self::FIXTURE_MANIFESTS . '/with-cli/commands.php',
        );

        self::assertSame(
            [
                [
                    'name' => 'shop:ping',
                    'description' => "Answer the shop module's health probe.",
                    'service' => CliModule::HANDLER_SERVICE,
                ],
                [
                    'name' => 'shop:report',
                    'description' => "Render the shop module's own report.",
                    'service' => CliModule::HANDLER_SERVICE,
                ],
            ],
            $entries,
        );
    }
}
