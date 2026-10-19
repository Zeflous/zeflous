<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Aot\AotLane;
use Zef\Framework\Container\Aot\AotOutcome;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerArming;
use Zef\Framework\Container\ContainerInstalls;
use Zef\Framework\Container\ContainerSingletons;
use Zef\Framework\ContainerBootstrapper;
use Zef\Framework\FrameworkDefaults;
use Zef\Test\Support\Container\Decorators\FileLogger;
use Zef\Test\Support\Container\Decorators\LoggerDecorators;
use Zef\Test\Support\Container\Decorators\PrefixedLogger;
use Zef\Test\Support\Container\Interception\InterceptionLog;
use Zef\Test\Support\Container\Interception\ObservingResolvedListener;
use Zef\Test\Support\Container\Interception\RecordingInterceptor;
use Zef\Test\Support\Container\Interception\RecordingResolvingListener;
use Zef\Test\Support\Container\Interception\ReplacedService;
use Zef\Test\Support\Container\Interception\ReplacingResolvedListener;

/**
 * The interception lane's boot behaviour, end to end: the knobs parsed
 * with the rest, the registry armed after the install loop and before
 * the warmup, the warmup riding the interception, the AOT artifact
 * round-trip (the interception materialising fresh every boot, the
 * digest folding both knobs), and the interplay with the decoration
 * lane (the onion wrapping a decorated identifier's whole chain as
 * one construction) (ROADMAP area 9's boot half).
 *
 * @internal
 */
#[CoversClass(ContainerBootstrapper::class)]
#[CoversClass(ContainerInstalls::class)]
#[CoversClass(ContainerArming::class)]
#[CoversClass(ContainerSingletons::class)]
final class InterceptedBootTest extends TestCase
{
    private string $artifactFile;

    #[Override]
    protected function setUp(): void
    {
        $this->artifactFile = sys_get_temp_dir() . '/zef-intercepted-boot-' . uniqid('', false) . '.php';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_file($this->artifactFile)) {
            return;
        }

        unlink($this->artifactFile);
    }

    public function testTheBootArmsTheKnobsAndTheResolutionRidesThem(): void
    {
        $container = $this->booted($this->config());

        self::assertTrue($container->interceptions()->armed());

        $answer = $container->get('app.target');
        self::assertInstanceOf(ReplacedService::class, $answer);
        self::assertInstanceOf(FileLogger::class, $answer->inner);

        self::assertSame(
            [
                'resolving: app.target',
                'boot-link > app.target',
                'boot-link < app.target',
                'replaced: app.target',
            ],
            $this->log($container)->lines(),
        );
    }

    public function testTheMaterialisationResolvesItsReferencesBeforeTheArm(): void
    {
        $container = $this->booted($this->config());

        // The log service itself was constructed through the plain lane,
        // at the materialisation -- no listener fired for it, because
        // the registry did not exist yet.
        self::assertNotContains('resolving: test.log', $this->log($container)->lines());
    }

    public function testTheWarmupRidesTheInterception(): void
    {
        $config = $this->config();
        $config['aot'] = ['enabled' => false, 'warm' => true];

        $container = $this->booted($config);

        // The warmed singleton was replaced before its memo record: the
        // listener fired exactly once, during the warmup itself.
        $answer = $container->get('app.target');
        self::assertInstanceOf(ReplacedService::class, $answer);
        self::assertSame(
            [
                'resolving: app.target',
                'boot-link > app.target',
                'boot-link < app.target',
                'replaced: app.target',
            ],
            $this->log($container)->lines(),
            'the get() after the warmup is a memo hit -- it fires nothing',
        );
    }

    public function testTheDefaultProfileBootsUnarmed(): void
    {
        $container = $this->booted(['services' => []]);

        self::assertFalse($container->interceptions()->armed());
    }

    public function testTheFirstBootDumpsAndTheSecondArmsFromTheLiveKnobs(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $first = $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());
        $firstOutcome = $first->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $firstOutcome);
        self::assertSame(AotLane::FullCompile, $firstOutcome->lane);

        $second = $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());
        $secondOutcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $secondOutcome);
        self::assertSame(AotLane::ArtifactLoaded, $secondOutcome->lane);

        // The interception materialises fresh from the live knobs on the
        // artifact lane too: the second boot's own log service carries
        // the second boot's own tape -- the framework's own reserved
        // services riding the interception like any other construction
        // (the outcome read above is itself in the tape).
        self::assertTrue($second->interceptions()->armed());
        $answer = $second->get('app.target');
        self::assertInstanceOf(FileLogger::class, $answer);
        self::assertSame(
            [
                'resolving: framework.aot',
                'boot-link > framework.aot',
                'boot-link < framework.aot',
                'observed: framework.aot',
                'resolving: app.target',
                'boot-link > app.target',
                'boot-link < app.target',
                'observed: app.target',
            ],
            $this->log($second)->lines(),
        );
    }

    public function testAChangedInterceptionIsAProvenStaleArtifact(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        $changed = $this->aotConfig();
        $changed['interceptors'][] = [
            'class' => RecordingInterceptor::class,
            'arguments' => ['second-link', ['service' => 'test.log']],
        ];

        $second = $containerBootstrapper->bootstrap(new Config($changed), new Container());
        $outcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::ArtifactBypassed, $outcome->lane);

        // The bypassed compile armed the changed set: two links now --
        // over the outcome service the assertion read, and over the
        // target.
        $second->get('app.target');
        self::assertSame(
            [
                'resolving: framework.aot',
                'boot-link > framework.aot',
                'second-link > framework.aot',
                'second-link < framework.aot',
                'boot-link < framework.aot',
                'observed: framework.aot',
                'resolving: app.target',
                'boot-link > app.target',
                'second-link > app.target',
                'second-link < app.target',
                'boot-link < app.target',
                'observed: app.target',
            ],
            $this->log($second)->lines(),
        );
    }

    public function testAChangedListenerSetIsAProvenStaleArtifact(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        $changed = $this->aotConfig();
        $changed['listeners']['resolving'] = [];

        $second = $containerBootstrapper->bootstrap(new Config($changed), new Container());
        $outcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::ArtifactBypassed, $outcome->lane);
    }

    public function testTheOnionWrapsADecoratedChainAsOneConstruction(): void
    {
        $config = $this->config(ObservingResolvedListener::class);
        $config['services']['app.logger'] = [
            'class' => FileLogger::class,
            'arguments' => ['app.log'],
        ];
        $config['decorators']['app.logger'] = [
            ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]],
        ];

        $container = $this->booted($config);

        $logger = $container->get('app.logger');
        self::assertInstanceOf(PrefixedLogger::class, $logger);
        self::assertSame('timing(file(app.log):hi)', $logger->line('hi'));

        $lines = $this->log($container)->lines();

        // The onion ran exactly once -- for the public identifier: the
        // machinery layers beneath it bypassed, the whole chain one
        // construction.
        self::assertSame(['app.logger'], $this->onionSubjects($lines));

        // The listeners observed every construction: the public
        // identifier first, the machinery layer beneath it after -- a
        // replacing listener answers for the machinery too (the
        // reference's own semantics), the typed seams it would break
        // the host's own record.
        self::assertSame('resolving: app.logger', $lines[0]);
        self::assertContains('resolving: @decor:app.logger:base', $lines);
        self::assertContains('observed: @decor:app.logger:base', $lines);
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
    public function testTheArtifactLanesWarmupRidesTheArmedInterception(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        // The first boot dumps the artifact (warm off: the dump alone).
        $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        $warm = $this->aotConfig();
        $warm['aot']['warm'] = true;

        $second = $containerBootstrapper->bootstrap(new Config($warm), new Container());
        $outcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::ArtifactLoaded, $outcome->lane);

        // The artifact lane's own order: armed before the warmup, so
        // the eagerly constructed singleton rode the interception
        // during the warmup itself (the tape's first four entries) --
        // and the get() after the warmup is a memo hit that fires
        // nothing, the outcome read the only later construction.
        $answer = $second->get('app.target');
        self::assertInstanceOf(FileLogger::class, $answer);
        self::assertSame(
            [
                'resolving: app.target',
                'boot-link > app.target',
                'boot-link < app.target',
                'observed: app.target',
                'resolving: framework.aot',
                'boot-link > framework.aot',
                'boot-link < framework.aot',
                'observed: framework.aot',
            ],
            $this->log($second)->lines(),
        );
    }

    private function booted(array $config): Container
    {
        return new ContainerBootstrapper()->bootstrap(new Config($config), new Container());
    }

    /**
     * The interception-armed configuration both boots share: the log
     * service (the observation channel), the target, one interceptor
     * link, one resolving listener, and one resolved listener -- the
     * replacement form by default (the seam's own proof), the
     * observation form where the assertions read instances the
     * replacement would re-shape.
     *
     * @param class-string $resolvedListener
     *
     * @return array<string, array<int|string, mixed>>
     */
    private function config(string $resolvedListener = ReplacingResolvedListener::class): array
    {
        return [
            'services' => [
                'test.log' => ['class' => InterceptionLog::class],
                'app.target' => ['class' => FileLogger::class, 'arguments' => ['app.log']],
            ],
            'interceptors' => [
                ['class' => RecordingInterceptor::class, 'arguments' => ['boot-link', ['service' => 'test.log']]],
            ],
            'listeners' => [
                'resolving' => [
                    ['class' => RecordingResolvingListener::class, 'arguments' => [['service' => 'test.log']]],
                ],
                'resolved' => [
                    ['class' => $resolvedListener, 'arguments' => [['service' => 'test.log']]],
                ],
            ],
        ];
    }

    /**
     * The AOT configuration: the same armed set plus the enabled knob
     * (the artifact's digest folds the interception knobs themselves),
     * under the observation form so the outcome services the
     * assertions read survive the listeners.
     *
     * @return array<string, array<int|string, mixed>>
     */
    private function aotConfig(): array
    {
        return ['aot' => ['enabled' => true, 'warm' => false], ...$this->config(ObservingResolvedListener::class)];
    }

    /**
     * The identifiers the onion's single link served, in order.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function onionSubjects(array $lines): array
    {
        $subjects = [];
        foreach ($lines as $line) {
            if (!str_starts_with($line, 'boot-link > ')) {
                continue;
            }

            $subjects[] = mb_substr($line, mb_strlen('boot-link > '));
        }

        return $subjects;
    }
}
