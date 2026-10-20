<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
