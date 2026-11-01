<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\ContainerModuleRegistrar;
use Zef\Framework\ModuleBootstrapper;
use Zef\Framework\ModuleClaims;
use Zef\Framework\ModuleContextFactory;
use Zef\Framework\ModuleManifestLoader;
use Zef\Framework\ModuleResolver;
use Zef\Framework\ModuleRoster;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleEntries;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleExceptionSpelling;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleName;
use Zef\Framework\Modules\ModuleOrder;
use Zef\Framework\Modules\ModuleRequires;
use Zef\Framework\Modules\ModuleVersion;
use Zef\Test\Modules\Fixtures\AlphaModule;
use Zef\Test\Modules\Fixtures\BetaModule;
use Zef\Test\Modules\Fixtures\FixtureLog;
use Zef\Test\Modules\Fixtures\GammaModule;
use Zef\Test\Modules\Fixtures\SecondSharedModule;
use Zef\Test\Modules\Fixtures\SharedIdModule;

/**
 * The registration phase over the real fixtures: the empty knob is the
 * honest no-op, the reversed knob registers in dependency order and files
 * the roster the boot phase walks, the container answers a module's
 * service, and every refusal -- the knob's grammar, an unanswerable
 * requirement, a shared identifier's second claimant -- fires before the
 * phase returns.
 *
 * @internal
 */
#[CoversClass(ModuleBootstrapper::class)]
#[CoversClass(ModuleRoster::class)]
#[CoversClass(ModuleResolver::class)]
#[CoversClass(ModuleManifestLoader::class)]
#[CoversClass(ContainerModuleRegistrar::class)]
#[CoversClass(ModuleClaims::class)]
#[CoversClass(ModuleContextFactory::class)]
#[CoversClass(ModuleEntries::class)]
#[CoversClass(ModuleOrder::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(ModuleName::class)]
#[CoversClass(ModuleVersion::class)]
#[CoversClass(ModuleRequires::class)]
#[CoversClass(ModuleExceptionSpelling::class)]
#[CoversClass(ModuleException::class)]
final class ModuleBootstrapperTest extends TestCase
{
    private Container $container;

    private ModuleBootstrapper $moduleBootstrapper;

    #[Override]
    protected function setUp(): void
    {
        $this->container = new Container();
        $this->moduleBootstrapper = new ModuleBootstrapper();
        FixtureLog::reset();
    }

    public function testTheEmptyKnobIsTheHonestNoOp(): void
    {
        $roster = $this->moduleBootstrapper->register(new Config([]), $this->container);

        self::assertSame([], FixtureLog::events());
        self::assertFalse($this->container->has('test.alpha.greeting'));

        $roster->boot();

        self::assertSame([], FixtureLog::events());
    }

    public function testTheReversedKnobRegistersInDependencyOrder(): void
    {
        $roster = $this->moduleBootstrapper->register(
            new Config(['modules' => [GammaModule::class, AlphaModule::class, BetaModule::class]]),
            $this->container,
        );

        self::assertSame(
            [
                'register:test/alpha',
                'register:test/beta',
                'register:test/gamma',
            ],
            FixtureLog::events(),
        );

        self::assertTrue($this->container->has('test.alpha.greeting'));
        self::assertSame('alpha', $this->container->get('test.alpha.greeting'));

        $roster->boot();

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
    }

    public function testTheManifestSpellingRidesTheSamePhase(): void
    {
        $manifest = __DIR__ . '/fixtures/alpha-manifest/module.php';

        $this->moduleBootstrapper->register(
            new Config(['modules' => [$manifest]]),
            $this->container,
        );

        self::assertSame(['register:test/alpha'], FixtureLog::events());
        self::assertTrue($this->container->has('test.alpha.greeting'));
    }

    public function testANonListKnobIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The 'modules' configuration must be a list of module entries; string arrived.",
        );

        $this->moduleBootstrapper->register(new Config(['modules' => 'nope']), $this->container);
    }

    public function testAGarbageEntryIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains("The module entry 'payment' is neither");

        $this->moduleBootstrapper->register(new Config(['modules' => ['payment']]), $this->container);
    }

    public function testAnUnanswerableRequirementIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/beta' requires 'test/alpha', which no declared module answers",
        );

        $this->moduleBootstrapper->register(
            new Config(['modules' => [BetaModule::class]]),
            $this->container,
        );
    }

    public function testASecondClaimantIsRefusedWithTheFirstNamed(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module 'test/shared-two' registers 'shared.id', which the module "
            . "'test/shared' already claimed",
        );

        $this->moduleBootstrapper->register(
            new Config(['modules' => [SharedIdModule::class, SecondSharedModule::class]]),
            $this->container,
        );
    }

    public function testADuplicateNameAcrossSpellingsIsRefused(): void
    {
        $manifest = __DIR__ . '/fixtures/alpha-manifest/module.php';

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module name 'test/alpha' is declared twice (entries 0 and 1)",
        );

        $this->moduleBootstrapper->register(
            new Config(['modules' => [AlphaModule::class, $manifest]]),
            $this->container,
        );
    }

    public function testASecondBootCallIsRefused(): void
    {
        $roster = $this->moduleBootstrapper->register(
            new Config(['modules' => [AlphaModule::class]]),
            $this->container,
        );

        $roster->boot();

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            'The module roster has already booted; a roster boots once per process.',
        );

        $roster->boot();
    }
}
