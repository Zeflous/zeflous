<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Aot\AotArtifact;
use Zef\Framework\Container\Aot\AotArtifactLazies;
use Zef\Framework\Container\Aot\AotLane;
use Zef\Framework\Container\Aot\AotLazies;
use Zef\Framework\Container\Aot\AotOutcome;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\AotBootLane;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerCompileLane;
use Zef\Framework\Container\ContainerRegistrations;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\LazyConstructions;
use Zef\Framework\Container\LazyDeferrals;
use Zef\Framework\Container\LazyIdentities;
use Zef\Framework\Container\LazyMarking;
use Zef\Framework\Container\LazyMaterialisations;
use Zef\Framework\Container\LazyRefusals;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceResolutions;
use Zef\Framework\Container\ServiceStore;
use Zef\Framework\ContainerBootstrapper;
use Zef\Framework\FrameworkDefaults;
use Zef\Test\Support\Container\Interception\InterceptionLog;
use Zef\Test\Support\Container\Interception\ObservingResolvedListener;
use Zef\Test\Support\Container\Interception\RecordingInterceptor;
use Zef\Test\Support\Container\Interception\RecordingResolvingListener;
use Zef\Test\Support\Container\Lazy\EngineFuel;
use Zef\Test\Support\Container\Lazy\EngineMode;
use Zef\Test\Support\Container\Lazy\HeavyEngine;
use Zef\Test\Support\Container\Lazy\UnbuildableHouse;
use Zef\Test\Support\Container\Namespaces\NamespacedWrapper;

/**
 * The lazy lane's boot behaviour, end to end: the declaration asked to
 * defer, the boot marked it, the resolution lane answered the native
 * proxy, and the guarded construction -- guard, onion and listeners --
 * ran at the proxy's first use, exactly as it would have at resolution,
 * just later (ROADMAP area 7's proxy half -- the boot half).
 *
 * @internal
 */
#[CoversClass(ContainerBootstrapper::class)]
#[CoversClass(ContainerCompileLane::class)]
#[CoversClass(ContainerRegistrations::class)]
#[CoversClass(AotBootLane::class)]
#[CoversClass(AotArtifact::class)]
#[CoversClass(AotArtifactLazies::class)]
#[CoversClass(AotLazies::class)]
#[CoversClass(ContainerAotCache::class)]
#[CoversClass(Container::class)]
#[CoversClass(LazyConstructions::class)]
#[CoversClass(LazyDeferrals::class)]
#[CoversClass(LazyIdentities::class)]
#[CoversClass(LazyMarking::class)]
#[CoversClass(LazyMaterialisations::class)]
#[CoversClass(LazyRefusals::class)]
#[CoversClass(ServiceResolutions::class)]
#[CoversClass(ServiceStore::class)]
final class LazyBootTest extends TestCase
{
    private string $artifactFile;

    #[Override]
    protected function setUp(): void
    {
        $this->artifactFile = sys_get_temp_dir() . '/zef-lazy-boot-' . uniqid('', false) . '.php';
        HeavyEngine::reset();
        EngineFuel::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        HeavyEngine::reset();
        EngineFuel::reset();

        if (!is_file($this->artifactFile)) {
            return;
        }

        unlink($this->artifactFile);
    }

    public function testTheBootDefersTheWholeConstructionChain(): void
    {
        $container = $this->booted($this->config());

        // Nothing constructed at the boot: the declaration deferred, and
        // its whole dependency chain deferred with it.
        self::assertSame(0, HeavyEngine::$constructions);
        self::assertSame(0, EngineFuel::$constructions);

        $engine = $container->get('app.engine');

        // The resolution answered the proxy: an instance of the declared
        // class itself, no construction behind it yet.
        self::assertInstanceOf(HeavyEngine::class, $engine);
        self::assertSame(0, HeavyEngine::$constructions);
        self::assertSame(0, EngineFuel::$constructions);

        // The facade's own reach-through answers the mark the boot
        // wrote -- the registry the resolution lanes consult.
        self::assertSame(HeavyEngine::class, $container->lazyIdentities()->classOf('app.engine'));
        self::assertNull($container->lazyIdentities()->classOf('app.fuel'));

        // The first use materialises once -- engine and fuel together.
        self::assertSame(7, $engine->thrust());
        self::assertSame(1, HeavyEngine::$constructions);
        self::assertSame(1, EngineFuel::$constructions);

        // Every further use forwards to the real instance PHP kept.
        self::assertSame(7, $engine->thrust());
        self::assertSame(1, HeavyEngine::$constructions);
    }

    public function testASingletonMemoisesTheProxyItself(): void
    {
        $container = $this->booted($this->config());

        $first = $container->get('app.engine');
        $second = $container->get('app.engine');

        // The memo holds the proxy: one materialisation behind it, the
        // singleton's own economics preserved by the deferral.
        self::assertInstanceOf(HeavyEngine::class, $first);
        self::assertInstanceOf(HeavyEngine::class, $second);
        self::assertSame($first, $second);
        self::assertSame(0, HeavyEngine::$constructions);

        self::assertSame(7, $first->thrust());
        self::assertSame(7, $second->thrust());
        self::assertSame(1, HeavyEngine::$constructions);
    }

    public function testATransientLazyServiceAnswersAFreshProxyPerResolution(): void
    {
        $config = $this->config();
        $config['services']['app.engine']['lifetime'] = 'transient';
        $container = $this->booted($config);

        $first = $container->get('app.engine');
        $second = $container->get('app.engine');

        // A transient answers a fresh proxy per resolution, each
        // materialising its own instance.
        self::assertInstanceOf(HeavyEngine::class, $first);
        self::assertInstanceOf(HeavyEngine::class, $second);
        self::assertNotSame($first, $second);
        self::assertSame(0, HeavyEngine::$constructions);

        self::assertSame(7, $first->thrust());
        self::assertSame(7, $second->thrust());
        self::assertSame(2, HeavyEngine::$constructions);
    }

    public function testTheGuardedConstructionRunsAtMaterialisationNotAtResolution(): void
    {
        $container = $this->booted($this->armedConfig());

        $engine = $container->get('app.engine');

        // The deferral held: nothing fired at the resolution -- not the
        // resolving listeners, not the onion, not the resolved
        // listeners, and no construction.
        self::assertInstanceOf(HeavyEngine::class, $engine);
        self::assertSame([], $this->log($container)->lines());
        self::assertSame(0, HeavyEngine::$constructions);

        // The first use runs the guarded call itself, later: the
        // guard, the onion and the listeners all fire in the lane's own
        // order -- the fuel's own guarded construction nested inside the
        // engine's onion, exactly as the eager spelling nests it.
        self::assertSame(7, $engine->thrust());
        self::assertSame(
            [
                'resolving: app.engine',
                'boot-link > app.engine',
                'resolving: app.fuel',
                'boot-link > app.fuel',
                'boot-link < app.fuel',
                'observed: app.fuel',
                'boot-link < app.engine',
                'observed: app.engine',
            ],
            $this->log($container)->lines(),
        );
        self::assertSame(1, HeavyEngine::$constructions);
    }

    public function testTheDecoratedTargetDefersItsReHomedBase(): void
    {
        $container = $this->booted($this->decoratedConfig());

        $wrapper = $container->get('wrapped.target');

        // The wrapper chain built around the base's proxy: the base's
        // deferral travelled with the re-homing, so the whole chain
        // answers while the base stays unconstructed.
        self::assertInstanceOf(NamespacedWrapper::class, $wrapper);
        self::assertSame(0, HeavyEngine::$constructions);

        // The chain's first use of the base materialises it.
        self::assertInstanceOf(HeavyEngine::class, $wrapper->inner);
        self::assertSame(3, $wrapper->inner->thrust());
        self::assertSame(1, HeavyEngine::$constructions);
    }

    public function testTheArtifactLaneBakesTheLazyMapAndLoadsIt(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        // The first boot compiles and dumps; the second loads the
        // artifact and marks its lazy identities from the baked map.
        $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        $second = $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        $outcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::ArtifactLoaded, $outcome->lane);

        $engine = $second->get('app.engine');
        self::assertInstanceOf(HeavyEngine::class, $engine);
        self::assertSame(0, HeavyEngine::$constructions);
        self::assertSame(7, $engine->thrust());
        self::assertSame(1, HeavyEngine::$constructions);

        // The second deferred declaration arrives through the same
        // baked map: both marks land, both deferrals hold.
        $spinner = $second->get('app.spinner');
        self::assertInstanceOf(HeavyEngine::class, $spinner);
        self::assertSame(1, HeavyEngine::$constructions);
        self::assertSame(3, $spinner->thrust());
        self::assertSame(2, HeavyEngine::$constructions);
    }

    public function testAChangedLazyKeyIsAProvenStaleArtifact(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        // The same declaration set with the deferral dropped: the digest
        // differs (it folds the declarations' lazy keys), the artifact
        // is never run, the full lane recompiles eagerly.
        $changed = $this->aotConfig();
        unset($changed['services']['app.engine']['lazy']);

        $second = $containerBootstrapper->bootstrap(new Config($changed), new Container());

        $outcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertNotSame(AotLane::ArtifactLoaded, $outcome->lane);

        $engine = $second->get('app.engine');
        self::assertInstanceOf(HeavyEngine::class, $engine);
        self::assertSame(1, HeavyEngine::$constructions, 'the eager spelling constructs at the resolution');
    }

    public function testASetReRegistrationForgetsTheMarkWithTheMemo(): void
    {
        $container = $this->booted($this->config());

        // The eager surface re-registers eagerly: the mark the boot
        // wrote is forgotten together with the memo, not guessed at.
        $container->set(
            'app.engine',
            static fn (): HeavyEngine => new HeavyEngine(new EngineFuel()),
        );

        $engine = $container->get('app.engine');
        self::assertInstanceOf(HeavyEngine::class, $engine);
        self::assertSame(1, HeavyEngine::$constructions, 'the re-registered factory runs eagerly');
    }

    public function testTheProgrammaticMarkDefersARegisteredFactory(): void
    {
        $container = new Container();
        $container->set(
            'app.engine',
            static fn (): HeavyEngine => new HeavyEngine(new EngineFuel(), 5),
        );
        $container->markLazy('app.engine', HeavyEngine::class);

        $engine = $container->get('app.engine');
        self::assertInstanceOf(HeavyEngine::class, $engine);
        self::assertSame(0, HeavyEngine::$constructions);

        self::assertSame(5, $engine->thrust());
        self::assertSame(1, HeavyEngine::$constructions);

        // The idempotent re-mark behind the same class is the boot's
        // own repeat spelling -- the memoised proxy answers unchanged.
        $container->markLazy('app.engine', HeavyEngine::class);
        $answered = $container->get('app.engine');
        self::assertInstanceOf(HeavyEngine::class, $answered);
        self::assertSame(5, $answered->thrust());
        self::assertSame(1, HeavyEngine::$constructions);
    }

    public function testTheProgrammaticMarkRefusesTheIdentifiersThatCannotDefer(): void
    {
        $container = new Container();

        try {
            $container->markLazy('app.unregistered', HeavyEngine::class);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.unregistered' cannot be marked lazy behind the class '"
                . HeavyEngine::class . "': the identifier carries no registration to defer "
                . '(register the service before marking it lazy).',
                $definitionException->getMessage(),
            );
        }

        $container->set('app.scoped', static fn (): EngineFuel => new EngineFuel(), ServiceLifetime::REQUEST);

        try {
            $container->markLazy('app.scoped', EngineFuel::class);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.scoped' cannot be marked lazy: a request-lifetime registration cannot defer "
                . 'its materialisation into a scope it cannot name.',
                $definitionException->getMessage(),
            );
        }

        $container->set('app.engine', static fn (): HeavyEngine => new HeavyEngine(new EngineFuel()));
        $container->markLazy('app.engine', HeavyEngine::class);

        try {
            $container->markLazy('app.engine', EngineMode::cases()[0]::class);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' is already marked lazy behind the class '" . HeavyEngine::class
                . "' and cannot be re-marked behind the class '" . EngineMode::cases()[0]::class
                . "' (re-register the service, or mark the identifier with one class only).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMarkAfterAResolvedAnswerRefusesInsteadOfDeferringSilently(): void
    {
        // The audit's own repro (#783): an identifier marked after its
        // resolution -- the memo's answer would answer every
        // resolution before the registry is consulted, a mark that
        // never takes effect. The refusal names the seam and the way
        // out; a mark the lane cannot honour is a mark it does not
        // accept.
        $container = new Container();
        $container->set(
            'app.engine',
            static fn (): HeavyEngine => new HeavyEngine(new EngineFuel(), 5),
        );
        $engine = $container->get('app.engine');
        self::assertSame(1, HeavyEngine::$constructions, 'the eager resolution built the instance');

        try {
            $container->markLazy('app.engine', HeavyEngine::class);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be marked lazy behind the class '"
                . HeavyEngine::class . "': the identifier has already been resolved and its answer is "
                . 'memoised -- the resolution lanes answer the memo before the registry is consulted, '
                . 'so the mark would never take effect (mark the identifier before its first resolution, '
                . 'or re-register it through set() -- which forgets the memo -- and mark again).',
                $definitionException->getMessage(),
            );
        }

        // The refusal leaves nothing behind: no mark in the registry,
        // the memoised instance still answering.
        self::assertNull($container->lazyIdentities()->classOf('app.engine'));
        self::assertSame($engine, $container->get('app.engine'));

        // The way out the refusal names: re-register through set() --
        // the memo and any mark forgotten together -- then mark again,
        // and the deferral takes effect on the fresh registration.
        $container->set(
            'app.engine',
            static fn (): HeavyEngine => new HeavyEngine(new EngineFuel(), 5),
        );
        $container->markLazy('app.engine', HeavyEngine::class);
        self::assertSame(HeavyEngine::class, $container->lazyIdentities()->classOf('app.engine'));

        $deferred = $container->get('app.engine');
        self::assertInstanceOf(HeavyEngine::class, $deferred);
        self::assertSame(1, HeavyEngine::$constructions, 'the fresh registration defers its construction');
        self::assertSame(5, $deferred->thrust());
        self::assertSame(2, HeavyEngine::$constructions);
    }

    public function testATransientMayBeMarkedAfterAResolution(): void
    {
        // The refusal is the memo's, not the resolution's: a transient
        // never memoises anywhere, so a mark after a resolution still
        // takes effect -- every further resolution answers a fresh
        // proxy, and the already-built instance stays where it was
        // answered, in the caller's own hands.
        $container = new Container();
        $container->set(
            'app.engine',
            static fn (): HeavyEngine => new HeavyEngine(new EngineFuel(), 5),
            ServiceLifetime::TRANSIENT,
        );
        $eager = $container->get('app.engine');
        self::assertInstanceOf(HeavyEngine::class, $eager);
        self::assertSame(1, HeavyEngine::$constructions);

        $container->markLazy('app.engine', HeavyEngine::class);

        $deferred = $container->get('app.engine');
        self::assertInstanceOf(HeavyEngine::class, $deferred);
        self::assertSame(1, HeavyEngine::$constructions, 'the transient answers a fresh proxy');
        self::assertSame(5, $deferred->thrust());
        self::assertSame(2, HeavyEngine::$constructions);
        self::assertSame(5, $eager->thrust());
    }

    public function testANonObjectFactoryAnswerBehindALazyMarkRefuses(): void
    {
        $container = new Container();
        $container->set('app.answer', static fn (): string => 'nope');
        $container->markLazy('app.answer', HeavyEngine::class);

        $proxy = $container->get('app.answer');
        self::assertInstanceOf(HeavyEngine::class, $proxy);

        try {
            $answered = $proxy->thrust();
            self::fail('Expected a DefinitionException to be thrown, answered ' . $answered);
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.answer' is lazy behind the class '" . HeavyEngine::class
                . "' but its factory answered string (a lazy proxy stands in for an object; "
                . 'drop the lazy declaration or answer an instance of the marked class).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheMissPathStaysHonestBehindTheMarks(): void
    {
        $container = $this->booted($this->config());

        self::assertTrue($container->has('app.engine'));
        self::assertFalse($container->has('app.unregistered'));
        self::assertSame(0, HeavyEngine::$constructions);
    }

    /**
     * The warm step's own scope, pinned (issue #800's finding 2, the
     * interaction documented): the knob constructs the EAGER singleton
     * lane alone -- a lazy-marked singleton keeps the deferral its
     * declaration owns across the warm step, the warmer answering the
     * native proxy with the factory never run. This is the recorded
     * posture the never-at-the-boot families spell through it (the
     * database and events lanes' silent knobs), not an escape: the
     * halt-the-boot promise reads the eager lane alone, and the
     * deferral's own failure answers at the first use.
     */
    public function testTheWarmStepConstructsTheEagerSingletonAndKeepsTheLazySingletonDeferred(): void
    {
        $container = $this->booted(['aot' => ['warm' => true], ...$this->warmServices()]);

        // The eager singleton (the fuel, unmarked) constructed at the
        // warm step; both lazy singletons (engine and spinner) kept
        // their deferral -- the memo holds each proxy, the factories
        // never ran.
        self::assertSame(0, HeavyEngine::$constructions);
        self::assertSame(1, EngineFuel::$constructions);

        // The marks ride the registry exactly as declared: the warm
        // step neither strips them nor runs behind them.
        self::assertSame(HeavyEngine::class, $container->lazyIdentities()->classOf('app.engine'));
        self::assertSame(HeavyEngine::class, $container->lazyIdentities()->classOf('app.spinner'));

        // The resolution answers the proxy: an instance of the declared
        // class itself, nothing constructed behind it yet.
        $engine = $container->get('app.engine');
        self::assertInstanceOf(HeavyEngine::class, $engine);
        self::assertSame(0, HeavyEngine::$constructions);

        // The first use materialises once -- the deferred construction
        // the declaration asked to run later, running later.
        self::assertSame(7, $engine->thrust());
        self::assertSame(1, HeavyEngine::$constructions);
    }

    /**
     * The finding's own repro, pinned as the recorded posture: a lazy
     * singleton whose factory throws does NOT halt the warm boot -- the
     * deferral the declaration owns keeps its failure at the first use,
     * where the host who declared the deferral meets it, with the
     * constructor's own message. The warm knob's halt-the-boot promise
     * reads the eager lane alone.
     */
    public function testALazySingletonWhoseFactoryFailsSurfacesAtTheFirstUseNeverAtTheWarmBoot(): void
    {
        $config = ['aot' => ['warm' => true], ...$this->warmServices()];
        $config['services']['app.unbuildable'] = [
            'class' => UnbuildableHouse::class,
            'lazy' => true,
        ];

        // The boot stays green: the warmer answered the proxy, the
        // factory never ran.
        $container = $this->booted($config);
        self::assertSame(0, HeavyEngine::$constructions);

        $house = $container->get('app.unbuildable');
        self::assertInstanceOf(UnbuildableHouse::class, $house);

        try {
            $entered = $house->enter();
            self::fail('Expected a RuntimeException to be thrown, entered ' . $entered);
        } catch (RuntimeException $runtimeException) {
            self::assertSame('the unbuildable house cannot be built', $runtimeException->getMessage());
        }
    }

    /**
     * The loaded lane's own twin: the artifact boot warms through the
     * same resolution lane, so the lazy singletons the artifact carries
     * keep their deferral across the loaded lane's own warm step too --
     * the recorded interaction riding both boots identically, and the
     * eager singleton (the fuel) constructed on the loaded boot.
     */
    public function testTheWarmStepKeepsTheLazyDeferralOnTheLoadedLaneToo(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $config = [
            'aot' => ['enabled' => true, 'warm' => true],
            'container' => ['mode' => 'production'],
            ...$this->warmServices(),
        ];

        $containerBootstrapper->bootstrap(new Config($config), new Container());

        HeavyEngine::reset();
        EngineFuel::reset();

        $loaded = $containerBootstrapper->bootstrap(new Config($config), new Container());

        // The loaded lane's own warm step: the eager fuel constructed,
        // both lazy singletons deferred, the marks riding the registry.
        self::assertSame(0, HeavyEngine::$constructions);
        self::assertSame(1, EngineFuel::$constructions);
        self::assertSame(HeavyEngine::class, $loaded->lazyIdentities()->classOf('app.engine'));

        $engine = $loaded->get('app.engine');
        self::assertInstanceOf(HeavyEngine::class, $engine);
        self::assertSame(0, HeavyEngine::$constructions);

        self::assertSame(7, $engine->thrust());
        self::assertSame(1, HeavyEngine::$constructions);
    }

    /**
     * The warm interaction's own governed set: the shared configuration
     * with both lazy singletons referencing the fuel explicitly, so the
     * warm walk's counts are the walk's own arithmetic alone (the
     * autowire lane's synthetic class-keyed birth stays out of the
     * numbers the assertions read).
     *
     * @return array<string, array<string, mixed>>
     */
    private function warmServices(): array
    {
        return [
            'services' => [
                'app.fuel' => ['class' => EngineFuel::class],
                'app.engine' => [
                    'class' => HeavyEngine::class,
                    'arguments' => [['service' => 'app.fuel'], 7],
                    'lazy' => true,
                ],
                'app.spinner' => [
                    'class' => HeavyEngine::class,
                    'arguments' => ['fuel' => ['service' => 'app.fuel'], 'thrust' => 3],
                    'lazy' => true,
                ],
            ],
        ];
    }

    /**
     * The booted container's own log service, typed for the tape the
     * assertions read.
     */
    private function log(Container $container): InterceptionLog
    {
        $log = $container->get('test.log');
        self::assertInstanceOf(InterceptionLog::class, $log);

        return $log;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function booted(array $config): Container
    {
        return new ContainerBootstrapper()->bootstrap(new Config($config), new Container());
    }

    /**
     * The governed configuration the lazy boots share: a fuel the engine
     * references, and the engine itself asking to defer behind its own
     * class.
     *
     * @return array{services: array<string, array<string, mixed>>}
     */
    private function config(): array
    {
        return [
            'services' => [
                'app.fuel' => ['class' => EngineFuel::class],
                'app.engine' => [
                    'class' => HeavyEngine::class,
                    'arguments' => [['service' => 'app.fuel'], 7],
                    'lazy' => true,
                ],
                'app.spinner' => [
                    'class' => HeavyEngine::class,
                    'arguments' => ['thrust' => 3],
                    'lazy' => true,
                ],
            ],
        ];
    }

    /**
     * The interception-armed configuration: the same lazy declaration
     * with one interceptor link and the observing listeners -- the tape
     * that proves the guarded call runs at materialisation.
     *
     * @return array{
     *     services: array<string, array<string, mixed>>,
     *     interceptors: list<array<string, mixed>>,
     *     listeners: array{resolving: list<array<string, mixed>>, resolved: list<array<string, mixed>>},
     * }
     */
    private function armedConfig(): array
    {
        return [
            'services' => [
                'test.log' => ['class' => InterceptionLog::class],
                ...$this->config()['services'],
            ],
            'interceptors' => [
                ['class' => RecordingInterceptor::class, 'arguments' => ['boot-link', ['service' => 'test.log']]],
            ],
            'listeners' => [
                'resolving' => [
                    ['class' => RecordingResolvingListener::class, 'arguments' => [['service' => 'test.log']]],
                ],
                'resolved' => [
                    ['class' => ObservingResolvedListener::class, 'arguments' => [['service' => 'test.log']]],
                ],
            ],
        ];
    }

    /**
     * The decorated-lazy configuration: the target asks to defer and
     * carries one decorator wrapper -- the deferral travelling with the
     * re-homed base.
     *
     * @return array{
     *     services: array<string, array<string, mixed>>,
     *     decorators: array<string, list<array<string, mixed>>>,
     * }
     */
    private function decoratedConfig(): array
    {
        return [
            'services' => [
                'app.fuel' => ['class' => EngineFuel::class],
                'wrapped.target' => [
                    'class' => HeavyEngine::class,
                    'arguments' => [['service' => 'app.fuel']],
                    'lazy' => true,
                ],
            ],
            'decorators' => [
                'wrapped.target' => [
                    ['class' => NamespacedWrapper::class, 'arguments' => ['wrapped', ['service' => '@inner']]],
                ],
            ],
        ];
    }

    /**
     * The AOT configuration: the same governed set plus the enabled
     * knob -- the artifact's digest folds the declarations' lazy keys.
     *
     * @return array{
     *     aot: array{enabled: bool, warm: bool},
     *     container: array{mode: 'production'},
     *     services: array<string, array<string, mixed>>,
     * }
     */
    private function aotConfig(): array
    {
        return [
            'aot' => ['enabled' => true, 'warm' => false],
            'container' => ['mode' => 'production'],
            ...$this->config(),
        ];
    }
}
