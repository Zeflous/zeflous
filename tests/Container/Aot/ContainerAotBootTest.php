<?php

declare(strict_types=1);

namespace Zef\Test\Container\Aot;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Aot\AotArguments;
use Zef\Framework\Container\Aot\AotArtifact;
use Zef\Framework\Container\Aot\AotArtifactCalls;
use Zef\Framework\Container\Aot\AotArtifactEntries;
use Zef\Framework\Container\Aot\AotArtifactEntry;
use Zef\Framework\Container\Aot\AotArtifactEnvelope;
use Zef\Framework\Container\Aot\AotArtifactIds;
use Zef\Framework\Container\Aot\AotBuildEntry;
use Zef\Framework\Container\Aot\AotClosureEntry;
use Zef\Framework\Container\Aot\AotClosures;
use Zef\Framework\Container\Aot\AotDefinitions;
use Zef\Framework\Container\Aot\AotDigest;
use Zef\Framework\Container\Aot\AotEntries;
use Zef\Framework\Container\Aot\AotException;
use Zef\Framework\Container\Aot\AotLane;
use Zef\Framework\Container\Aot\AotLifetimes;
use Zef\Framework\Container\Aot\AotLiterals;
use Zef\Framework\Container\Aot\AotMap;
use Zef\Framework\Container\Aot\AotMembers;
use Zef\Framework\Container\Aot\AotOutcome;
use Zef\Framework\Container\Aot\AotRefusals;
use Zef\Framework\Container\Aot\AotToggles;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\AotBootLane;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerCompileLane;
use Zef\Framework\Container\ContainerCompilePipeline;
use Zef\Framework\Container\ContainerCompilePlan;
use Zef\Framework\Container\ContainerRegistrations;
use Zef\Framework\Container\ContainerServices;
use Zef\Framework\Container\ContainerSingletons;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\ServiceTagIndex;
use Zef\Framework\Container\SingletonWarmer;
use Zef\Framework\Container\TaggedServices;
use Zef\Framework\ContainerBootstrapper;
use Zef\Framework\ContainerKnobs;
use Zef\Framework\FrameworkDefaults;
use Zef\Test\Support\Container\Aot\BrokenHouse;
use Zef\Test\Support\Container\Aot\CallsHouse;
use Zef\Test\Support\Container\Aot\EnumHouse;
use Zef\Test\Support\Container\Aot\FactoryProduct;
use Zef\Test\Support\Container\Aot\FactorySource;
use Zef\Test\Support\Container\Aot\House;
use Zef\Test\Support\Container\Aot\PlainSink;
use Zef\Test\Support\Container\Aot\Roof;
use Zef\Test\Support\Container\Aot\Tone;
use Zef\Test\Support\Container\Aot\UnrenderableHouse;
use Zef\Test\Support\Container\Aot\WarmCounter;
use Zef\Test\Support\Container\Aot\Window;
use Zef\Test\Support\Container\Injector\BillingService;
use Zef\Test\Support\Container\Injector\MockGateway;
use Zef\Test\Support\Container\Injector\PaymentGateway;
use Zef\Test\Support\Container\Injector\StripeGateway;
use Zef\Test\Support\Container\Parameters\ConnectionHouse;

/**
 * The compile/dump lane's boot behaviour: the two lanes the
 * bootstrapper answers with, their outcome service, the artifact's
 * lifecycle (absent, dumped, loaded, proven stale and replaced, torn),
 * and the warmup orchestration -- over a definition set rich enough
 * that the equivalence between the lanes is the claim the file pins.
 *
 * @internal
 */
#[CoversClass(ContainerBootstrapper::class)]
#[CoversClass(ContainerCompilePipeline::class)]
#[CoversClass(ContainerCompilePlan::class)]
#[CoversClass(SingletonWarmer::class)]
#[CoversClass(AotMap::class)]
#[CoversClass(AotToggles::class)]
#[CoversClass(AotDigest::class)]
#[CoversClass(AotOutcome::class)]
#[CoversClass(AotLane::class)]
#[CoversClass(AotRefusals::class)]
#[CoversClass(AotEntries::class)]
#[CoversClass(AotArtifact::class)]
#[CoversClass(AotDefinitions::class)]
#[CoversClass(ContainerAotCache::class)]
#[CoversClass(AotClosures::class)]
#[CoversClass(AotArguments::class)]
#[CoversClass(AotLiterals::class)]
#[CoversClass(ServiceTagIndex::class)]
#[CoversClass(AotArtifactCalls::class)]
#[CoversClass(AotArtifactEntries::class)]
#[CoversClass(AotArtifactEntry::class)]
#[CoversClass(AotArtifactEnvelope::class)]
#[CoversClass(AotArtifactIds::class)]
#[CoversClass(AotBuildEntry::class)]
#[CoversClass(AotClosureEntry::class)]
#[CoversClass(AotLifetimes::class)]
#[CoversClass(AotMembers::class)]
#[CoversClass(AotException::class)]
#[CoversClass(AotBootLane::class)]
#[CoversClass(ContainerCompileLane::class)]
#[CoversClass(ContainerRegistrations::class)]
#[CoversClass(ContainerSingletons::class)]
#[CoversClass(ContainerServices::class)]
#[CoversClass(ContainerKnobs::class)]
final class ContainerAotBootTest extends TestCase
{
    private string $artifactFile;

    #[Override]
    protected function setUp(): void
    {
        $this->artifactFile = sys_get_temp_dir() . '/zef-aot-boot-' . uniqid('', false) . '.php';
        WarmCounter::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_file($this->artifactFile)) {
            return;
        }

        unlink($this->artifactFile);
    }

    public function testTheDefaultBootAnswersTheFullCompileOutcome(): void
    {
        $container = $this->fullBoot();

        $outcome = $container->get(FrameworkDefaults::AOT_SERVICE);

        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::FullCompile, $outcome->lane);
        self::assertSame('the aot knob is off', $outcome->note);
        self::assertSame(17, $outcome->definitions);
        self::assertSame(
            [
                'app.calls',
                'app.alias',
                'app.answer',
                'app.built',
                'app.transient',
                'app.lone',
                'app.connection',
                PaymentGateway::class,
                'billing',
            ],
            $outcome->unreferenced,
        );
    }

    public function testTheEnabledKnobWithoutACacheSeamRefuses(): void
    {
        try {
            $this->booter()->bootstrap(new Config($this->config(['enabled' => true])), new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'aot.enabled' knob is on but no container AOT cache is wired -- "
                . 'pass one to the application bootstrapper, or spell the knob false.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAPlanTheBoundaryCannotRenderDegradesTheDumpWithoutCrashingTheBoot(): void
    {
        // The audit's crash shape: a constructor default the full lane
        // answers happily but the artifact boundary cannot spell. The
        // boot must survive through the full lane, name the degradation
        // in its own outcome, and lay no artifact down.
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $config = $this->config(['enabled' => true]);
        $config['services']['app.unrenderable'] = ['class' => UnrenderableHouse::class, 'autowire' => true];

        $container = $containerBootstrapper->bootstrap(new Config($config), new Container());

        $outcome = $container->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::FullCompile, $outcome->lane);
        self::assertSame(
            'the plan carries a value the artifact boundary cannot render -- '
            . 'the full lane answered this boot and laid no artifact down',
            $outcome->note,
        );
        self::assertFalse($containerAotCache->exists());

        $house = $container->get('app.unrenderable');
        self::assertInstanceOf(UnrenderableHouse::class, $house);
        self::assertInstanceOf(PlainSink::class, $house->sink, 'the full lane answered the defaulted construction');
    }

    public function testAnEnumDefaultedServiceSurvivesTheArtifactRoundTrip(): void
    {
        // The one object shape the boundary CAN spell: the enum case
        // renders as its constant expression, and the artifact lane
        // answers the same singleton the full lane baked.
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $config = $this->config(['enabled' => true]);
        $config['services']['app.tone'] = ['class' => EnumHouse::class, 'autowire' => true];

        $first = $containerBootstrapper->bootstrap(new Config($config), new Container());
        $firstHouse = $first->get('app.tone');
        self::assertInstanceOf(EnumHouse::class, $firstHouse);
        self::assertSame(Tone::CALM, $firstHouse->tone);

        $second = $containerBootstrapper->bootstrap(new Config($config), new Container());
        $secondHouse = $second->get('app.tone');
        self::assertInstanceOf(EnumHouse::class, $secondHouse);
        self::assertSame(
            Tone::CALM,
            $secondHouse->tone,
            'the artifact lane answers the enum singleton the full lane baked',
        );
    }

    public function testTheFirstBootDumpsAndTheSecondLoads(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $first = $containerBootstrapper->bootstrap(new Config($this->config(['enabled' => true])), new Container());

        $firstOutcome = $first->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $firstOutcome);
        self::assertSame(AotLane::FullCompile, $firstOutcome->lane);
        self::assertSame(
            'the artifact was absent -- compiled and dumped for the next boot',
            $firstOutcome->note,
        );
        self::assertTrue($containerAotCache->exists());

        $second = $containerBootstrapper->bootstrap(new Config($this->config(['enabled' => true])), new Container());

        $secondOutcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $secondOutcome);
        self::assertSame(AotLane::ArtifactLoaded, $secondOutcome->lane);
        self::assertSame('the artifact answered the boot', $secondOutcome->note);
        self::assertSame(17, $secondOutcome->definitions);
        self::assertSame(
            [
                'app.calls',
                'app.alias',
                'app.answer',
                'app.built',
                'app.transient',
                'app.lone',
                'app.connection',
                PaymentGateway::class,
                'billing',
            ],
            $secondOutcome->unreferenced,
        );
    }

    public function testTheLoadedLaneAnswersEquivalently(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->config(['enabled' => true])), new Container());

        $loaded = $containerBootstrapper->bootstrap(new Config($this->config(['enabled' => true])), new Container());
        $full = $this->fullBoot();

        $fullHouse = $full->get('app.house');
        $loadedHouse = $loaded->get('app.house');

        self::assertInstanceOf(House::class, $fullHouse);
        self::assertInstanceOf(House::class, $loadedHouse);
        self::assertSame($fullHouse->window->glazing, $loadedHouse->window->glazing);
        self::assertSame($fullHouse->panes, $loadedHouse->panes);
        self::assertSame($fullHouse->trim, $loadedHouse->trim);

        self::assertSame($loadedHouse, $loaded->get('app.alias'));

        $fullCalls = $full->get('app.calls');
        $loadedCalls = $loaded->get('app.calls');
        self::assertInstanceOf(CallsHouse::class, $fullCalls);
        self::assertInstanceOf(CallsHouse::class, $loadedCalls);
        self::assertNotNull($fullCalls->roof);
        self::assertNotNull($loadedCalls->roof);
        self::assertSame($fullCalls->roof->material, $loadedCalls->roof->material);
        self::assertSame($fullCalls->weather, $loadedCalls->weather);
        self::assertSame($fullCalls->degrees, $loadedCalls->degrees);

        self::assertSame($full->get('app.answer'), $loaded->get('app.answer'));

        $fullBuilt = $full->get('app.built');
        $loadedBuilt = $loaded->get('app.built');
        self::assertInstanceOf(FactoryProduct::class, $fullBuilt);
        self::assertInstanceOf(FactoryProduct::class, $loadedBuilt);
        self::assertSame($fullBuilt->frame, $loadedBuilt->frame);
        self::assertSame($fullBuilt->window->glazing, $loadedBuilt->window->glazing);

        self::assertNotSame($loaded->get('app.transient'), $loaded->get('app.transient'));

        $fullConnection = $full->get('app.connection');
        $loadedConnection = $loaded->get('app.connection');
        self::assertInstanceOf(ConnectionHouse::class, $fullConnection);
        self::assertInstanceOf(ConnectionHouse::class, $loadedConnection);
        self::assertSame($fullConnection->host, $loadedConnection->host);
        self::assertSame($fullConnection->port, $loadedConnection->port);
        self::assertSame('localhost', $loaded->get('@value:db.host'));

        $billing = $loaded->get('billing');
        self::assertInstanceOf(BillingService::class, $billing);
        self::assertSame('charged:stripe', $billing->charge());
        self::assertInstanceOf(StripeGateway::class, $billing->gateway);
    }

    public function testTheTagsLocatorAnswersOnBothLanes(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->config(['enabled' => true])), new Container());

        $loaded = $containerBootstrapper->bootstrap(new Config($this->config(['enabled' => true])), new Container());

        $full = $this->fullBoot();

        $fullLocator = $full->get(FrameworkDefaults::TAGS_SERVICE);
        $loadedLocator = $loaded->get(FrameworkDefaults::TAGS_SERVICE);
        self::assertInstanceOf(TaggedServices::class, $fullLocator);
        self::assertInstanceOf(TaggedServices::class, $loadedLocator);

        self::assertSame($fullLocator->ids('frame.house'), $loadedLocator->ids('frame.house'));
        self::assertSame(['app.house'], $loadedLocator->ids('frame.house'));
        self::assertSame([], $loadedLocator->ids('frame.absent'));
    }

    public function testAProvenStaleArtifactIsNeverRunAndIsReplaced(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->config(['enabled' => true])), new Container());

        // The configuration changes: the artifact's digest no longer
        // describes it, so it must never run -- the boot compiles afresh
        // and lays a fresh artifact down.
        $changed = $this->config(['enabled' => true]);
        $changed['services']['app.extra'] = ['value' => 'late arrival'];

        $second = $containerBootstrapper->bootstrap(new Config($changed), new Container());

        $outcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::ArtifactBypassed, $outcome->lane);
        self::assertSame(
            'the artifact digest did not match the configuration -- recompiled and re-dumped',
            $outcome->note,
        );
        self::assertSame('late arrival', $second->get('app.extra'));

        $third = $containerBootstrapper->bootstrap(new Config($changed), new Container());
        $thirdOutcome = $third->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $thirdOutcome);
        self::assertSame(AotLane::ArtifactLoaded, $thirdOutcome->lane);
        self::assertSame('late arrival', $third->get('app.extra'));
        self::assertSame(18, $thirdOutcome->definitions);
    }

    public function testATornArtifactRefusesTheBoot(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->config(['enabled' => true])), new Container());

        file_put_contents($this->artifactFile, "<?php\nreturn [\n    'digest' => ");

        try {
            $containerBootstrapper->bootstrap(new Config($this->config(['enabled' => true])), new Container());
            self::fail('Expected an AotException to be thrown.');
        } catch (AotException $aotException) {
            self::assertStringStartsWith(
                "The container AOT artifact '{$this->artifactFile}' is not valid PHP (",
                $aotException->getMessage(),
            );
        }
    }

    public function testTheWarmKnobEagerlyConstructsSingletonsOnTheFullLane(): void
    {
        $config = $this->config(['warm' => true]);
        $config['services']['app.warm'] = ['class' => WarmCounter::class];

        $this->booter()->bootstrap(new Config($config), new Container());

        self::assertSame(1, WarmCounter::$constructions);
    }

    public function testWithoutTheWarmKnobNothingConstructsBeforeTheFirstGet(): void
    {
        $config = $this->config();
        $config['services']['app.warm'] = ['class' => WarmCounter::class];

        $container = $this->booter()->bootstrap(new Config($config), new Container());

        self::assertSame(0, WarmCounter::$constructions);
        $container->get('app.warm');
        self::assertSame(1, WarmCounter::$constructions);
    }

    public function testTheWarmKnobWorksOnTheLoadedLaneToo(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $config = $this->config(['enabled' => true, 'warm' => true]);
        $config['services']['app.warm'] = ['class' => WarmCounter::class];

        $containerBootstrapper->bootstrap(new Config($config), new Container());

        WarmCounter::reset();

        $loaded = $containerBootstrapper->bootstrap(new Config($config), new Container());

        $loadedOutcome = $loaded->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $loadedOutcome);
        self::assertSame(AotLane::ArtifactLoaded, $loadedOutcome->lane);
        self::assertSame(1, WarmCounter::$constructions);
    }

    public function testWithoutTheWarmKnobTheLoadedLaneStaysLazy(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $config = $this->config(['enabled' => true]);
        $config['services']['app.warm'] = ['class' => WarmCounter::class];

        $containerBootstrapper->bootstrap(new Config($config), new Container());

        WarmCounter::reset();

        $loaded = $containerBootstrapper->bootstrap(new Config($config), new Container());

        $loadedOutcome = $loaded->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $loadedOutcome);
        self::assertSame(AotLane::ArtifactLoaded, $loadedOutcome->lane);
        self::assertSame(0, WarmCounter::$constructions);

        $loaded->get('app.warm');
        self::assertSame(1, WarmCounter::$constructions);
    }

    public function testWarmingHaltsTheBootWhenConstructionFails(): void
    {
        $config = $this->config(['warm' => true]);
        $config['services']['app.broken'] = ['class' => BrokenHouse::class];

        try {
            $this->booter()->bootstrap(new Config($config), new Container());
            self::fail('Expected a RuntimeException to be thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('the broken house cannot be built', $runtimeException->getMessage());
        }
    }

    public function testTransientDefinitionsAreNeverWarmed(): void
    {
        $config = $this->config(['warm' => true]);
        $config['services']['app.flitting'] = ['class' => WarmCounter::class, 'lifetime' => 'transient'];

        $container = $this->booter()->bootstrap(new Config($config), new Container());

        self::assertSame(0, WarmCounter::$constructions);
        $container->get('app.flitting');
        self::assertSame(1, WarmCounter::$constructions);
    }

    public function testAKnobOffBootIgnoresAPresentArtifact(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config(self::config(['enabled' => true])), new Container());

        // The artifact exists and its digest would match -- but the knob
        // is off, so the full lane must answer, not the artifact one.
        $container = $containerBootstrapper->bootstrap(new Config(self::config()), new Container());

        $outcome = $container->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::FullCompile, $outcome->lane);
        self::assertSame('the aot knob is off', $outcome->note);
        self::assertInstanceOf(House::class, $container->get('app.house'));
    }

    public function testAKnobOffBootNeverDumpsAnArtifact(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $container = $containerBootstrapper->bootstrap(new Config($this->config()), new Container());

        $outcome = $container->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::FullCompile, $outcome->lane);
        self::assertFileDoesNotExist($this->artifactFile);
    }

    public function testTheFrameworkServicesKnobInstallsItsDeclarations(): void
    {
        $config = $this->config();
        $config['framework'] = ['services' => ['app.reserved' => ['class' => Window::class]]];

        $container = $this->booter()->bootstrap(new Config($config), new Container());

        self::assertInstanceOf(Window::class, $container->get('app.reserved'));
    }

    public function testTheFingerprintFoldsTheFrameworkServicesMap(): void
    {
        $withReserved = $this->config();
        $withReserved['framework'] = ['services' => ['app.reserved' => ['class' => Window::class]]];

        $withoutReserved = $this->config();
        $withoutReserved['framework'] = ['services' => []];

        self::assertSame(
            ContainerKnobs::parsed(new Config($withReserved))->fingerprint(),
            ContainerKnobs::parsed(new Config($withReserved))->fingerprint(),
        );
        self::assertNotSame(
            ContainerKnobs::parsed(new Config($withReserved))->fingerprint(),
            ContainerKnobs::parsed(new Config($withoutReserved))->fingerprint(),
        );
    }

    public function testASetWithAnUnresolvableReferenceRefusesTheCompile(): void
    {
        $config = self::config();
        $config['services']['app.hungry'] = [
            'class' => Window::class,
            'arguments' => [['service' => 'app.absent']],
        ];

        try {
            self::booter()->bootstrap(new Config($config), new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.hungry' references the unknown service 'app.absent'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAHostRebindOfTheOutcomeServiceWins(): void
    {
        $config = $this->config(['enabled' => true]);
        $config['services'][FrameworkDefaults::AOT_SERVICE] = ['value' => 'the host owns the answer'];

        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $first = $containerBootstrapper->bootstrap(new Config($config), new Container());
        self::assertSame('the host owns the answer', $first->get(FrameworkDefaults::AOT_SERVICE));

        $loaded = $containerBootstrapper->bootstrap(new Config($config), new Container());
        self::assertSame('the host owns the answer', $loaded->get(FrameworkDefaults::AOT_SERVICE));
    }

    /**
     * The environment doctrine's own half of the artifact contract: a
     * development boot holds the lane to the fresh compile on the dump
     * side -- the knob may be on and the cache wired, but no artifact
     * is ever laid down ({@see ContainerMode}, the performance area's
     * "cache disesuaikan lingkungan").
     */
    public function testADevelopmentBootHoldsTheLaneToTheFreshCompileAndNeverDumps(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $developmentConfig = $this->config(['enabled' => true]);
        $developmentConfig['container'] = ['mode' => 'development'];

        $container = $containerBootstrapper->bootstrap(new Config($developmentConfig), new Container());

        $outcome = $container->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::FullCompile, $outcome->lane);
        self::assertSame(
            'development mode holds the artifact lane to the fresh compile -- '
            . 'the artifact is neither probed nor dumped in development',
            $outcome->note,
        );
        self::assertFalse($containerAotCache->exists());
        self::assertFileDoesNotExist($this->artifactFile);
    }

    /**
     * The install side of the same doctrine: an artifact a production
     * boot laid down is *not probed* by a development boot -- the digest
     * proves configuration freshness, never source freshness, and
     * development is the environment where source churn is the normal
     * state. The artifact file rides through the dev boot untouched.
     */
    public function testADevelopmentBootDoesNotProbeAPresentArtifact(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->config(['enabled' => true])), new Container());

        self::assertTrue($containerAotCache->exists());
        $artifactBefore = (string) file_get_contents($this->artifactFile);

        $developmentConfig = $this->config(['enabled' => true]);
        $developmentConfig['container'] = ['mode' => 'development'];

        $container = $containerBootstrapper->bootstrap(new Config($developmentConfig), new Container());

        $outcome = $container->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::FullCompile, $outcome->lane);
        self::assertSame(
            'development mode holds the artifact lane to the fresh compile -- '
            . 'the artifact is neither probed nor dumped in development',
            $outcome->note,
        );
        self::assertSame($artifactBefore, (string) file_get_contents($this->artifactFile));
    }

    /**
     * The auto default with no `APP_ENV` in the environment answers
     * production, so the unpinned boot keeps the full artifact contract
     * -- the fail-closed side of the fold, pinned here because the test
     * process's environment is the one thing a suite must never trust.
     */
    public function testTheAutoModeWithoutAnEnvironmentKeepsTheArtifactContract(): void
    {
        $previousEnvironment = getenv('APP_ENV');

        try {
            putenv('APP_ENV');

            $containerAotCache = new ContainerAotCache($this->artifactFile);
            $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

            $unpinnedConfig = $this->config(['enabled' => true]);
            unset($unpinnedConfig['container']);

            $containerBootstrapper->bootstrap(new Config($unpinnedConfig), new Container());

            self::assertTrue($containerAotCache->exists());
        } finally {
            if ($previousEnvironment === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $previousEnvironment);
            }
        }
    }

    /**
     * The rich definition set both lanes must answer identically: the
     * four shapes, method-call injection, the `#[Value]` consumer, the
     * contextual rewrite, tags, the transient lifetime, and one
     * declaration nothing references.
     *
     * @param array<string, mixed> $aot the AOT knob's own value
     *
     * @return array<string, array<string, mixed>> the knob maps, each under
     *                                             its own key
     */
    private function config(array $aot = []): array
    {
        return [
            'services' => [
                'app.window' => ['class' => Window::class],
                'app.house' => [
                    'class' => House::class,
                    'arguments' => [['service' => 'app.window'], 4, ['style' => 'georgian']],
                    'tags' => ['frame.house'],
                ],
                'app.calls' => [
                    'class' => CallsHouse::class,
                    'arguments' => [['service' => 'app.window']],
                    'calls' => [
                        ['method' => 'addRoof', 'arguments' => [['service' => 'app.roof']]],
                        ['method' => 'noteWeather', 'arguments' => ['sunny', 21]],
                    ],
                ],
                'app.roof' => ['class' => Roof::class],
                'app.alias' => ['alias' => 'app.house'],
                'app.answer' => ['value' => ['plain' => 'data', 'numbers' => [1, 2, 3]]],
                'app.built' => [
                    'factory' => FactorySource::class . '::make',
                    'arguments' => [['service' => 'app.window'], 'steel'],
                ],
                'app.transient' => ['class' => Window::class, 'lifetime' => 'transient'],
                'app.lone' => ['value' => 'nobody references me'],
                'app.connection' => ['class' => ConnectionHouse::class, 'autowire' => true],
                'gateway.mock' => ['class' => MockGateway::class],
                PaymentGateway::class => ['alias' => 'gateway.mock'],
                'billing' => ['class' => BillingService::class, 'autowire' => true],
                'gateway.stripe' => ['class' => StripeGateway::class],
            ],
            'parameters' => [
                'db' => ['host' => 'localhost', 'port' => 3306],
            ],
            'tags' => ['interfaces' => []],
            'contextual' => [
                'billing' => [PaymentGateway::class => 'gateway.stripe'],
            ],
            'aot' => $aot,
            'container' => ['mode' => 'production'],
        ];
    }

    private function booter(): ContainerBootstrapper
    {
        return new ContainerBootstrapper();
    }

    /**
     * Boots the rich set on the full lane and answers the container.
     */
    private function fullBoot(): Container
    {
        return $this->booter()->bootstrap(new Config($this->config()), new Container());
    }
}
