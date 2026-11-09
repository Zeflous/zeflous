<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Console\FrameworkVerbs;
use Zef\Test\Modules\Fixtures\CliModule;
use Zef\Test\Modules\Fixtures\FixtureLog;
use Zef\Test\Modules\Fixtures\PingHandler;
use Zef\Test\Support\Tooling\CommandOutcome;
use Zef\Test\Support\Tooling\DeclaredCommands;

/**
 * The declared-command dispatch as a pure outcome: the seam's own
 * judgement order exactly as the joined block spelled it -- the
 * framework-verb exclusion answering null (the CLI's own branches own
 * those verbs, the seam stays silent so the branches below it speak),
 * the configuration directory's own refusal (the missing or
 * file-shaped spelling), the booted registry's unknown-verb refusal
 * with the way out the directory spells, the handler's own exit code
 * passed through verbatim over the arguments after the configuration
 * directory, and the boot's or the resolution's refusal surfacing with
 * the dispatch's own refusal word.
 *
 * @internal
 */
#[CoversClass(DeclaredCommands::class)]
#[CoversClass(CommandOutcome::class)]
final class DeclaredCommandsTest extends TestCase
{
    private const string FIXTURE_MANIFESTS = __DIR__ . '/../Modules/fixtures';

    #[Override]
    protected function setUp(): void
    {
        FixtureLog::reset();
    }

    /**
     * The exclusion is the seam's first judgement and its most
     * safety-critical one: every verb the framework's own vocabulary
     * carries answers null, so the CLI's own branches -- the
     * positional-path commands included -- are never intercepted by the
     * registry. A declared command with the same spelling could not
     * even exist: the registry refuses the collision.
     */
    public function testEveryFrameworkVerbAnswersNullAndStaysSilent(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-cli/module.php'],
        ]);

        try {
            foreach (array_keys(FrameworkVerbs::all()) as $verb) {
                \assert($verb !== '');

                $outcome = DeclaredCommands::run($verb, [$root]);

                self::assertNull($outcome, $verb);
            }
        } finally {
            $this->removeConfigs($root);
        }
    }

    public function testAMissingConfigurationDirectoryIsAPathDefectNamingIt(): void
    {
        $outcome = DeclaredCommands::run('shop:ping', ['/does/not/exist']);

        self::assertNotNull($outcome);
        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            "Configuration directory does not exist: /does/not/exist\n",
            $outcome->stderr,
        );
    }

    /**
     * A file standing in the configuration directory position resolves
     * through realpath yet is no directory: the refusal names the
     * operator's own spelling, exactly the joined block's judgement.
     */
    public function testAFileStandingInTheConfigurationDirectoryPositionIsAPathDefect(): void
    {
        $outcome = DeclaredCommands::run('shop:ping', [__FILE__]);

        self::assertNotNull($outcome);
        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            'Configuration directory does not exist: ' . __FILE__ . "\n",
            $outcome->stderr,
        );
    }

    public function testAnAbsentConfigurationDirectoryPositionIsAPathDefect(): void
    {
        $outcome = DeclaredCommands::run('shop:ping', []);

        self::assertNotNull($outcome);
        self::assertSame(2, $outcome->exitCode);
        self::assertSame(
            "Configuration directory does not exist: \n",
            $outcome->stderr,
        );
    }

    public function testAnUnknownVerbOverABootedHostAnswersTheWayOut(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-cli/module.php'],
        ]);

        try {
            $outcome = DeclaredCommands::run('shop:missing', [$root]);

            self::assertNotNull($outcome);
            self::assertSame(2, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertSame(
                "Unknown command 'shop:missing'. Run 'bin/zef list <config-directory>'"
                . " to see the declared commands.\n",
                $outcome->stderr,
            );
        } finally {
            $this->removeConfigs($root);
        }
    }

    /**
     * The answered path's shape: the handler owns its own terminals, so
     * the outcome carries the handler's exit code alone -- empty bytes
     * on both streams -- and the arguments AFTER the configuration
     * directory reach the handler verbatim, the directory never leaking
     * into them.
     */
    public function testADeclaredVerbResolvesItsHandlerAndPassesItsExitCodeThrough(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-cli/module.php'],
        ]);

        try {
            $outcome = DeclaredCommands::run('shop:ping', [$root, 'one', 'two']);

            self::assertNotNull($outcome);
            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertSame('', $outcome->stderr);
            self::assertSame(['command:one,two'], FixtureLog::events());
        } finally {
            $this->removeConfigs($root);
        }
    }

    /**
     * The handler's own exit codes ride through verbatim: the contract's
     * whole vocabulary, not the dispatch's.
     */
    public function testTheHandlersOwnRefusalsRideThroughVerbatim(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-cli/module.php'],
        ]);

        try {
            $operational = DeclaredCommands::run('shop:ping', [$root, '--refuse']);
            $usage = DeclaredCommands::run('shop:ping', [$root, '--usage']);

            self::assertNotNull($operational);
            self::assertNotNull($usage);
            self::assertSame(1, $operational->exitCode);
            self::assertSame(2, $usage->exitCode);
            self::assertSame(['command:--refuse', 'command:--usage'], FixtureLog::events());
        } finally {
            $this->removeConfigs($root);
        }
    }

    /**
     * The host's own declarations dispatch through the same seam as the
     * module's: the joined table's every entry, not one source of it.
     */
    public function testAHostDeclaredVerbDispatchesThroughTheSameSeam(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [],
            'console.php' => [
                'commands' => [
                    ['name' => 'host:ping', 'description' => 'Host ping.', 'service' => CliModule::HANDLER_SERVICE],
                ],
            ],
            'services.php' => [
                CliModule::HANDLER_SERVICE => [
                    'class' => PingHandler::class,
                    'arguments' => [],
                ],
            ],
        ]);

        try {
            $outcome = DeclaredCommands::run('host:ping', [$root, 'from', 'the', 'host']);

            self::assertNotNull($outcome);
            self::assertSame(0, $outcome->exitCode);
            self::assertSame(['command:from,the,host'], FixtureLog::events());
        } finally {
            $this->removeConfigs($root);
        }
    }

    /**
     * A boot that refuses answers the dispatch's own refusal word with
     * the failing unit's chain: the malformed console table halts the
     * one-call boot, the operator reads the dispatch's word.
     */
    public function testAHostWhoseBootRefusesAnswersTheDispatchRefusal(): void
    {
        $root = $this->stageConfigs([
            'console.php' => [
                'commands' => [['name' => 'host:ping', 'descriptionn' => 'typo']],
            ],
        ]);

        try {
            $outcome = DeclaredCommands::run('host:ping', [$root]);

            self::assertNotNull($outcome);
            self::assertSame(1, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertStringStartsWith('The command dispatch refused: ', $outcome->stderr);
            self::assertStringEndsWith("\n", $outcome->stderr);
            self::assertStringContainsString('description', $outcome->stderr);
        } finally {
            $this->removeConfigs($root);
        }
    }

    /**
     * A declaration whose service does not answer the CommandHandler
     * contract refuses through the dispatch's fail-closed resolution --
     * the framework.config service is a real service that is no
     * handler, so the resolution's own refusal word surfaces.
     */
    public function testAServiceThatIsNoHandlerRefusesThroughTheDispatch(): void
    {
        $root = $this->stageConfigs([
            'console.php' => [
                'commands' => [
                    ['name' => 'host:ping', 'description' => 'Host ping.', 'service' => 'framework.config'],
                ],
            ],
        ]);

        try {
            $outcome = DeclaredCommands::run('host:ping', [$root]);

            self::assertNotNull($outcome);
            self::assertSame(1, $outcome->exitCode);
            self::assertStringStartsWith('The command dispatch refused: ', $outcome->stderr);
            self::assertStringEndsWith("\n", $outcome->stderr);
            self::assertStringContainsString('does not implement CommandHandler', $outcome->stderr);
        } finally {
            $this->removeConfigs($root);
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
        $root = sys_get_temp_dir() . '/zef-declared-commands-' . uniqid('', false);
        mkdir($root, 0o777, true);

        foreach ($files as $name => $payload) {
            file_put_contents(
                $root . '/' . $name,
                '<?php return ' . var_export($payload, true) . ';',
            );
        }

        return $root;
    }

    /**
     * Removes the staged configuration directory.
     *
     * @param non-empty-string $root
     */
    private function removeConfigs(string $root): void
    {
        foreach ((array) glob($root . '/*') as $entry) {
            if (!\is_string($entry) || !is_file($entry)) {
                continue;
            }

            unlink($entry);
        }

        rmdir($root);
    }
}
