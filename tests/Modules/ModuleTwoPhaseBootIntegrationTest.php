<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\ContainerModuleRegistrar;
use Zef\Framework\ModuleBootstrapper;
use Zef\Framework\ModuleClaims;
use Zef\Framework\ModuleContextFactory;
use Zef\Framework\ModuleManifestLoader;
use Zef\Framework\ModuleResolver;
use Zef\Framework\ModuleRoster;
use Zef\Framework\Modules\ModuleConfigRefusals;
use Zef\Framework\Modules\ModuleConfigSchema;
use Zef\Framework\Modules\ModuleConstructions;
use Zef\Framework\Modules\ModuleContext;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleEntries;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleManifest;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleName;
use Zef\Framework\Modules\ModuleOrder;
use Zef\Framework\Modules\ModuleOrderBook;
use Zef\Framework\Modules\ModuleOrderRefusals;
use Zef\Framework\Modules\ModuleRequires;
use Zef\Framework\Modules\ModuleSchemaRules;
use Zef\Framework\Modules\ModuleVersion;
use Zef\Test\Modules\Fixtures\AlphaModule;
use Zef\Test\Modules\Fixtures\BetaModule;
use Zef\Test\Modules\Fixtures\EchoContextModule;
use Zef\Test\Modules\Fixtures\FixtureLog;
use Zef\Test\Modules\Fixtures\GammaModule;
use Zef\Test\Modules\Fixtures\HostGreeter;

/**
 * The module engine riding the one-call boot: the reversed knob boots the
 * chain in dependency order, a host's own declarative definition resolves
 * through a module-registered service (the register-before-definitions
 * order's own proof), the namespaced configuration block arrives at the
 * module's context validated by its manifest schema, and the
 * block-without-schema posture refuses the boot with the module named.
 *
 * @internal
 */
#[CoversClass(ApplicationBootstrapper::class)]
#[CoversClass(ModuleBootstrapper::class)]
#[CoversClass(ModuleRoster::class)]
#[CoversClass(ModuleResolver::class)]
#[CoversClass(ModuleManifestLoader::class)]
#[CoversClass(ModuleManifest::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(ModuleName::class)]
#[CoversClass(ModuleVersion::class)]
#[CoversClass(ModuleRequires::class)]
#[CoversClass(ModuleEntries::class)]
#[CoversClass(ModuleOrder::class)]
#[CoversClass(ModuleOrderBook::class)]
#[CoversClass(ModuleOrderRefusals::class)]
#[CoversClass(ModuleConstructions::class)]
#[CoversClass(ContainerModuleRegistrar::class)]
#[CoversClass(ModuleClaims::class)]
#[CoversClass(ModuleContextFactory::class)]
#[CoversClass(ModuleConfigSchema::class)]
#[CoversClass(ModuleSchemaRules::class)]
#[CoversClass(ModuleConfigRefusals::class)]
#[CoversClass(ModuleContext::class)]
#[CoversClass(ModuleException::class)]
final class ModuleTwoPhaseBootIntegrationTest extends TestCase
{
    private const string FIXTURE_MANIFESTS = __DIR__ . '/fixtures';

    #[Override]
    protected function setUp(): void
    {
        FixtureLog::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        FixtureLog::reset();
    }

    public function testTheOneCallBootRunsBothPhasesInDependencyOrder(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [GammaModule::class, AlphaModule::class, BetaModule::class],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);

        self::assertSame(
            [
                'register:test/alpha',
                'register:test/beta',
                'register:test/gamma',
                'boot:test/alpha',
                'boot:test/beta',
                'boot:test/gamma',
            ],
            FixtureLog::events(),
        );

        self::assertSame('alpha', $kernel->container()->get('test.alpha.greeting'));
    }

    public function testAHostDefinitionResolvesThroughAModuleRegistration(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [AlphaModule::class],
            'services.php' => [
                'host.greeter' => [
                    'class' => HostGreeter::class,
                    'arguments' => [['service' => 'test.alpha.greeting']],
                ],
            ],
        ]);

        $kernel = new ApplicationBootstrapper()->boot($root);

        $greeter = $kernel->container()->get('host.greeter');

        self::assertInstanceOf(HostGreeter::class, $greeter);
        self::assertSame('alpha', $greeter->greeting());
    }

    public function testTheNamespacedBlockArrivesValidatedAtTheContext(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [self::FIXTURE_MANIFESTS . '/with-schema/module.php'],
            'test.php' => ['echo' => ['endpoint' => 'http://x', 'retries' => ['one']]],
        ]);

        new ApplicationBootstrapper()->boot($root);

        self::assertSame(
            [
                'register:test/echo',
                'boot:test/echo@test/echo@1.0.0@{"endpoint":"http://x","retries":["one"]}',
            ],
            FixtureLog::events(),
        );
    }

    public function testABlockWithoutASchemaRefusesTheBootWithTheModuleNamed(): void
    {
        $root = $this->stageConfigs([
            'modules.php' => [EchoContextModule::class],
            'test.php' => ['echo' => ['endpoint' => 'http://x']],
        ]);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/echo' carries the configuration block 'test.echo' "
            . 'but declares no config_schema',
        );

        new ApplicationBootstrapper()->boot($root);
    }

    /**
     * Stages a throwaway configuration directory whose every file returns
     * the given array -- the file-equals-key convention's own spelling.
     *
     * @param array<non-empty-string, array<array-key, mixed>|list<string>> $files
     *
     * @return non-empty-string
     */
    private function stageConfigs(array $files): string
    {
        $root = sys_get_temp_dir() . '/zef-module-boot-' . uniqid('', false);
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
