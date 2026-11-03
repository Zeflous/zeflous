<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\CliSchema;
use Zef\Framework\ConsoleFamilies;
use Zef\Framework\ModuleCliEntries;
use Zef\Framework\ModuleCliTable;
use Zef\Framework\ModuleManifestLoader;
use Zef\Framework\ModuleRoster;
use Zef\Framework\Modules\ModuleCliRefusals;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Test\Modules\Fixtures\CliModule;

/**
 * The CLI extension point's consumption fold: the merged table, the
 * honest identity posture, the host-first join order and every refusal's
 * sentence.
 *
 * @internal
 */
#[CoversClass(ModuleCliTable::class)]
#[CoversClass(ModuleCliEntries::class)]
#[CoversClass(ModuleCliRefusals::class)]
#[CoversClass(ModuleRoster::class)]
#[CoversClass(ModuleManifestLoader::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(CliSchema::class)]
#[CoversClass(ConsoleFamilies::class)]
#[CoversClass(ConfigException::class)]
#[CoversClass(ModuleException::class)]
final class ModuleCliTableTest extends TestCase
{
    public function testARosterWithoutDeclaredCommandsLeavesTheRepositoryVerbatim(): void
    {
        $config = new Config([
            'console' => [
                'commands' => [$this->declaration('host:ping')],
            ],
        ]);

        $augmented = new ModuleCliTable()->augmented($config, $this->roster([]));

        self::assertSame($config, $augmented);
    }

    public function testTheModuleEntriesJoinAfterTheHostEntriesUnderTheOneKey(): void
    {
        $config = new Config([
            'console' => [
                'commands' => [$this->declaration('host:ping')],
            ],
        ]);

        $augmented = new ModuleCliTable()->augmented($config, $this->roster([
            $this->stagedCommands([
                $this->declaration('shop:ping'),
            ]),
        ]));

        self::assertSame(
            [
                $this->declaration('host:ping'),
                $this->declaration('shop:ping'),
            ],
            $augmented->get('console.commands'),
        );
    }

    public function testTheCommandsKeyIsCreatedWhenTheHostDeclaresNone(): void
    {
        $augmented = new ModuleCliTable()->augmented(new Config(), $this->roster([
            $this->stagedCommands([
                $this->declaration('shop:ping'),
            ]),
        ]));

        self::assertSame(
            [$this->declaration('shop:ping')],
            $augmented->get('console.commands'),
        );
    }

    public function testASecondModuleJoinsInRosterOrder(): void
    {
        $augmented = new ModuleCliTable()->augmented(new Config(), $this->roster([
            $this->stagedCommands([
                $this->declaration('shop:ping'),
            ]),
            $this->stagedCommands([
                $this->declaration('audit:trail'),
            ], 'test/audit'),
        ]));

        self::assertSame(
            [
                $this->declaration('shop:ping'),
                $this->declaration('audit:trail'),
            ],
            $augmented->get('console.commands'),
        );
    }

    public function testAModuleWithoutCommandsDoesNotStopTheDeclarationsBehindIt(): void
    {
        $augmented = new ModuleCliTable()->augmented(new Config(), $this->roster([
            new ModuleDeclaration(CliModule::class, new ModuleMetadata('test/silent', '1.0.0')),
            $this->stagedCommands([
                $this->declaration('shop:ping'),
            ]),
        ]));

        self::assertSame(
            [$this->declaration('shop:ping')],
            $augmented->get('console.commands'),
        );
    }

    public function testTheModuleEntriesRideVerbatimNamesAndAll(): void
    {
        $declared = [
            'name' => 'shop:ping',
            'description' => "Answer the shop module's health probe.",
            'service' => 'test/shop/ping.handler',
        ];

        $augmented = new ModuleCliTable()->augmented(new Config(), $this->roster([
            $this->stagedCommands([$declared]),
        ]));

        self::assertSame([$declared], $augmented->get('console.commands'));
    }

    public function testAHostCommandsTableThatIsNotAnArrayRefusesTheFold(): void
    {
        $config = new Config(['console' => ['commands' => 'nope']]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains(
            'The "console.commands" configuration must be a list of command declarations.',
        );

        new ModuleCliTable()->augmented($config, $this->roster([
            $this->stagedCommands([
                $this->declaration('shop:ping'),
            ]),
        ]));
    }

    public function testAMissingCliFileRefusesWithTheModuleNamed(): void
    {
        $missing = sys_get_temp_dir() . '/zef-cli-missing-' . uniqid('', false) . '.php';

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/shop' declares the cli file its boot cannot load: "
            . "The module cli file '{$missing}' does not exist (a module-side path "
            . 'is spelled absolute, rooted at its own directory).',
        );

        new ModuleCliTable()->augmented(new Config(), $this->roster([
            new ModuleDeclaration(
                CliModule::class,
                new ModuleMetadata('test/shop', '1.0.0'),
                null,
                null,
                null,
                $missing,
            ),
        ]));
    }

    public function testASchemaViolatingEntryRefusesWithTheModuleNamed(): void
    {
        $bad = sys_get_temp_dir() . '/zef-cli-bad-' . uniqid('', false) . '.php';
        file_put_contents(
            $bad,
            "<?php return [['name' => 'shop:ping', 'descriptionn' => 'Ping.']];",
        );

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/shop' declares a cli file the Standard CLI Schema refuses: "
            . 'Configuration failed schema validation: description: expected a non-empty string,'
            . ' got missing; service: expected a non-empty string, got missing; descriptionn:'
            . ' expected one of name, description, service, got an unexpected key.',
        );

        new ModuleCliTable()->augmented(new Config(), $this->roster([
            new ModuleDeclaration(
                CliModule::class,
                new ModuleMetadata('test/shop', '1.0.0'),
                null,
                null,
                null,
                $bad,
            ),
        ]));
    }

    public function testACliFileThatIsNotAnArrayRefusesWithTheModuleNamed(): void
    {
        $notAnArray = sys_get_temp_dir() . '/zef-cli-scalar-' . uniqid('', false) . '.php';
        file_put_contents($notAnArray, '<?php return "not-an-array";');

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/shop' declares the cli file its boot cannot load",
        );

        new ModuleCliTable()->augmented(new Config(), $this->roster([
            new ModuleDeclaration(
                CliModule::class,
                new ModuleMetadata('test/shop', '1.0.0'),
                null,
                null,
                null,
                $notAnArray,
            ),
        ]));
    }

    /**
     * One command declaration in its minimal spelling.
     *
     * @return array<string, mixed>
     */
    private function declaration(string $name): array
    {
        return [
            'name' => $name,
            'description' => 'One line purpose.',
            'service' => $name . '.handler',
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function stagedCommands(array $entries, string $module = 'test/shop'): ModuleDeclaration
    {
        $root = sys_get_temp_dir() . '/zef-cli-table-' . uniqid('', false);
        mkdir($root, 0o777, true);

        $commands = $root . '/commands.php';
        file_put_contents($commands, '<?php return ' . var_export($entries, true) . ';');

        return new ModuleDeclaration(
            CliModule::class,
            new ModuleMetadata($module, '1.0.0'),
            null,
            null,
            null,
            $commands,
        );
    }

    /**
     * @param list<ModuleDeclaration> $declarations
     */
    private function roster(array $declarations): ModuleRoster
    {
        $moduleRoster = new ModuleRoster(new Config());

        foreach ($declarations as $declaration) {
            $moduleRoster->add($declaration, new CliModule());
        }

        return $moduleRoster;
    }
}
