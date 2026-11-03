<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\CliSchema;
use Zef\Framework\Console\ConsoleCommandEntry;
use Zef\Framework\Console\ConsoleCommands;
use Zef\Framework\ConsoleWiring;
use Zef\Framework\FrameworkDefaults;
use Zef\Test\Modules\Fixtures\CliModule;
use Zef\Test\Modules\Fixtures\FixtureLog;
use Zef\Test\Support\Tooling\CliListJson;
use Zef\Test\Support\Tooling\CliListText;

/**
 * The CLI extension point riding the one-call boot: a module's declared
 * commands file joins the frozen repository's `console.commands` table
 * (appended after the host's own, the names verbatim), the
 * `framework.config` service answers the same merged repository the
 * kernel carries, the command registry's reserved registration answers
 * the joined declarations at its first resolution, the dispatch
 * resolves the ONE handler the operator's verb declared through the
 * booted container and passes its exit code through verbatim, and the
 * directory renders the joined table.
 *
 * @internal
 */
#[CoversClass(ApplicationBootstrapper::class)]
#[CoversClass(ConsoleWiring::class)]
#[CoversClass(ConsoleCommands::class)]
#[CoversClass(CliSchema::class)]
#[CoversClass(CliListText::class)]
#[CoversClass(CliListJson::class)]
#[CoversClass(ConfigException::class)]
final class ModuleCliIntegrationTest extends TestCase
{
    private const string FIXTURE_MANIFESTS = __DIR__ . '/fixtures';

    #[Override]
    protected function setUp(): void
    {
        FixtureLog::reset();
    }

    public function testAModuleCommandsFileJoinsTheBootedRepositoryAfterTheHostEntries(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-cli/module.php'],
            'console.php' => [
                'commands' => [
                    ['name' => 'host:ping', 'description' => 'Host ping.', 'service' => 'host.ping'],
                ],
            ],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);

        self::assertSame(
            [
                ['name' => 'host:ping', 'description' => 'Host ping.', 'service' => 'host.ping'],
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
            $kernel->config()->get('console.commands'),
        );

        $serviceConfig = $kernel->container()->get(FrameworkDefaults::CONFIG_SERVICE);
        self::assertInstanceOf(Config::class, $serviceConfig);
        self::assertSame(
            $kernel->config()->get('console.commands'),
            $serviceConfig->get('console.commands'),
        );
    }

    public function testTheReservedRegistrationAnswersTheJoinedDeclarations(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-cli/module.php'],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);
        $commands = $kernel->container()->get(ConsoleWiring::COMMANDS_SERVICE);

        self::assertInstanceOf(ConsoleCommands::class, $commands);
        self::assertSame(2, $commands->count());
        self::assertSame(
            CliModule::HANDLER_SERVICE,
            $commands->named('shop:ping')?->service,
        );
    }

    public function testTheDispatchResolvesTheDeclaredHandlerAndPassesItsExitCodeThrough(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-cli/module.php'],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);
        $commands = ConsoleWiring::commands($kernel->config());

        $entry = $commands->named('shop:ping');
        self::assertInstanceOf(ConsoleCommandEntry::class, $entry);

        $handler = ConsoleWiring::handler($kernel->container(), $entry);

        self::assertSame(0, $handler->run(['one', 'two']));
        self::assertSame(['command:one,two'], FixtureLog::events());

        self::assertSame(1, $handler->run(['--refuse']));
        self::assertSame(2, $handler->run(['--usage']));
    }

    public function testTheDirectoryRendersTheJoinedTable(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-cli/module.php'],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);
        $commands = ConsoleWiring::commands($kernel->config());

        $text = CliListText::table($commands);

        self::assertStringContainsString('Zef commands -- 15 framework, 2 declared', $text);
        self::assertStringContainsString('framework:', $text);
        self::assertStringContainsString('route:list', $text);
        self::assertStringContainsString('declared:', $text);
        self::assertStringContainsString(
            "shop:ping  Answer the shop module's health probe.",
            $text,
        );
        self::assertStringContainsString(
            "shop:report  Render the shop module's own report.",
            $text,
        );
    }

    public function testTheDirectoryRendersTheHonestEmptyOverAModulelessBoot(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);
        $commands = ConsoleWiring::commands($kernel->config());

        self::assertSame(0, $commands->count());

        $text = CliListText::table($commands);

        self::assertStringContainsString('Zef commands -- 15 framework, 0 declared', $text);
        self::assertStringContainsString('declared:', $text);

        $decoded = json_decode(CliListJson::rendered($commands), true);
        self::assertIsArray($decoded);

        self::assertSame(0, $decoded['count']);
        self::assertSame([], $decoded['commands']);

        $framework = $decoded['framework'] ?? null;
        self::assertIsArray($framework);
        self::assertArrayHasKey('route:list', $framework);
    }

    public function testTheJsonDirectoryCarriesTheFrameworkAndDeclaredSections(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-cli/module.php'],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);
        $commands = ConsoleWiring::commands($kernel->config());

        $decoded = json_decode(CliListJson::rendered($commands), true);
        self::assertIsArray($decoded);

        self::assertSame(2, $decoded['count']);

        $commandEntries = $decoded['commands'] ?? null;
        self::assertIsArray($commandEntries);
        self::assertSame(
            [
                'name' => 'shop:ping',
                'description' => "Answer the shop module's health probe.",
                'service' => CliModule::HANDLER_SERVICE,
            ],
            $commandEntries[0],
        );

        $framework = $decoded['framework'] ?? null;
        self::assertIsArray($framework);
        self::assertArrayHasKey('storage:map', $framework);
        self::assertSame(
            "Answer the storage layout's lane map for a declared root.",
            $framework['storage:map'],
        );
    }

    public function testAMalformedHostCommandsTableHaltsTheBoot(): void
    {
        $root = $this->stageConfigs([
            'console.php' => [
                'commands' => [['name' => 'host:ping', 'descriptionn' => 'typo']],
            ],
        ]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageIsOrContains('description');

        new ApplicationBootstrapper()->boot($root);
    }

    public function testAModuleCommandsFileThatCollidesWithAFrameworkVerbRefusesAtTheRegistrysFirstResolution(): void
    {
        // The collision is the registry fold's own judgement, so it
        // surfaces at the registry's first resolution -- the same
        // never-at-the-boot discipline the wired dispatcher's own
        // already-defined refusal carries. The boot itself succeeds: the
        // boot's own assertion is the Standard CLI Schema's shape
        // question (the cache-artifact guard), never the registry's
        // duplicate and collision judgements.
        $manifests = sys_get_temp_dir() . '/zef-cli-collide-' . uniqid('', false);
        mkdir($manifests, 0o777, true);
        file_put_contents($manifests . '/module.php', '<?php return ' . var_export([
            'name' => 'test/shop',
            'version' => '1.0.0',
            'class' => CliModule::class,
            'cli' => './commands.php',
        ], true) . ';');
        file_put_contents($manifests . '/commands.php', '<?php return ' . var_export([
            ['name' => 'route:list', 'description' => 'Shadow.', 'service' => 'x'],
        ], true) . ';');

        $root = $this->stageConfigs([
            'modules.php' => [$manifests . '/module.php'],
        ]);

        try {
            $kernel = new ApplicationBootstrapper()->boot($root);

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessageIsOrContains(
                "The command 'route:list' collides with the framework's own verb",
            );

            ConsoleWiring::commands($kernel->config());
        } finally {
            unlink($manifests . '/module.php');
            unlink($manifests . '/commands.php');
            rmdir($manifests);
        }
    }

    /**
     * Stages a configuration directory the one-call boot answers from.
     *
     * @param array<string, mixed> $files
     *
     * @return non-empty-string
     */
    private function stageConfigs(array $files): string
    {
        $root = sys_get_temp_dir() . '/zef-cli-boot-' . uniqid('', false);
        mkdir($root, 0o777, true);

        foreach ($files as $name => $payload) {
            file_put_contents(
                $root . '/' . $name,
                '<?php return ' . var_export($payload, true) . ';',
            );
        }

        return $root;
    }
}
