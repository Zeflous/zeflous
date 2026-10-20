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
use Zef\Framework\Container\AotBootLane;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerCompileLane;
use Zef\Framework\Container\ContainerInventory;
use Zef\Framework\Container\ContainerRegistrations;
use Zef\Framework\Container\InventoryEntries;
use Zef\Framework\Container\InventoryMembers;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\ContainerBootstrapper;
use Zef\Framework\FrameworkDefaults;
use Zef\Test\Support\Container\Lazy\EngineFactory;
use Zef\Test\Support\Container\Lazy\EngineFuel;
use Zef\Test\Support\Container\Lazy\HeavyEngine;
use Zef\Test\Support\Container\Namespaces\NamespacedService;
use Zef\Test\Support\Container\Namespaces\NamespacedWrapper;

/**
 * The debug inventory's read side (ROADMAP area 12): the declarations
 * the boot spelled, answered as data through the reserved
 * `framework.inventory` service on both lanes -- the recipe per shape,
 * the declared dependencies, the live lazy marks, and the snapshot
 * semantics the two clocks keep.
 *
 * @internal
 */
#[CoversClass(ContainerInventory::class)]
#[CoversClass(InventoryEntries::class)]
#[CoversClass(InventoryMembers::class)]
#[CoversClass(ContainerRegistrations::class)]
#[CoversClass(ContainerCompileLane::class)]
#[CoversClass(AotBootLane::class)]
final class ContainerInventoryTest extends TestCase
{
    private string $artifactFile;

    #[Override]
    protected function setUp(): void
    {
        $this->artifactFile = sys_get_temp_dir() . '/zef-inventory-' . uniqid('', false) . '.php';
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

    public function testTheInventoryAnswersTheBootDeclarationsPerShape(): void
    {
        $container = $this->booted();
        $inventory = $this->inventory($container);

        self::assertSame(
            ['app.window', 'app.house', 'app.short', 'app.engine', 'app.fuel', 'app.answer'],
            $inventory->ids(),
        );

        $window = $inventory->entry('app.window');
        self::assertNotNull($window);
        self::assertSame('app.window', $window->id);
        self::assertSame('class', $window->shape);
        self::assertSame(NamespacedService::class, $window->class);
        self::assertNull($window->factory);
        self::assertNull($window->alias);
        self::assertNull($window->value);
        self::assertSame('singleton', $window->lifetime);
        self::assertSame(['frame.window'], $window->tags);
        self::assertSame([], $window->dependencies);
        self::assertNull($window->lazy);

        $house = $inventory->entry('app.house');
        self::assertNotNull($house);
        self::assertSame(['app.window'], $house->dependencies, 'the declared service references, walked');

        $short = $inventory->entry('app.short');
        self::assertNotNull($short);
        self::assertSame('alias', $short->shape);
        self::assertSame('app.window', $short->alias);

        $engine = $inventory->entry('app.engine');
        self::assertNotNull($engine);
        self::assertSame('factory', $engine->shape);
        self::assertSame(EngineFactory::class . '::make', $engine->factory);
        self::assertSame(['app.fuel'], $engine->dependencies);
        self::assertSame(['thrust'], $engine->calls, 'the method-call injections, by name');

        $fuel = $inventory->entry('app.fuel');
        self::assertNotNull($fuel);
        self::assertSame(
            ['burn', 'burn'],
            $fuel->calls,
            'the method-call injections answer in declaration order, repeats included',
        );

        $answer = $inventory->entry('app.answer');
        self::assertNotNull($answer);
        self::assertSame('value', $answer->shape);
        self::assertSame(42, $answer->value);

        self::assertNull($inventory->entry('app.absent'));
    }

    public function testTheLazyMarksAreReadLive(): void
    {
        $container = $this->booted();
        $container->set(
            'app.engine',
            static fn (): HeavyEngine => new HeavyEngine(new EngineFuel(), 5),
            ServiceLifetime::TRANSIENT,
        );
        $container->set(
            'app.late',
            static fn (): HeavyEngine => new HeavyEngine(new EngineFuel()),
        );

        // The inventory service materialises first; the mark lands
        // after -- and the next read still answers it.
        $inventory = $this->inventory($container);
        self::assertSame([], $inventory->lazy());

        $container->markLazy('app.late', HeavyEngine::class);
        self::assertSame(['app.late' => HeavyEngine::class], $inventory->lazy());
        self::assertNull($inventory->entry('app.late'), 'an undeclared identifier carries no entry, mark or not');

        // A `set()` re-registration forgets the mark with the memo --
        // the live registry answers that too.
        $container->set('app.late', static fn (): int => 7);
        self::assertSame([], $inventory->lazy());
    }

    public function testTheMachineryMarksStayOutOfTheLazyView(): void
    {
        $container = $this->booted();
        $container->set(
            '@decor:app.window:base',
            static fn (): NamespacedService => new NamespacedService('base'),
        );
        $container->markLazy('@decor:app.window:base', NamespacedService::class);

        // Two host marks land after the machinery mark -- the walk must
        // continue past the machinery identifier, not stop at it, and
        // the host-facing view answers every mark it walked.
        $container->set(
            'app.first',
            static fn (): HeavyEngine => new HeavyEngine(new EngineFuel()),
        );
        $container->set(
            'app.second',
            static fn (): HeavyEngine => new HeavyEngine(new EngineFuel()),
        );
        $container->markLazy('app.first', HeavyEngine::class);
        $container->markLazy('app.second', HeavyEngine::class);

        $inventory = $this->inventory($container);

        // The registry holds the machinery mark; the host-facing lazy
        // view does not carry the boot's machinery identifiers -- and
        // the marks that follow the machinery id still answer.
        self::assertSame(NamespacedService::class, $container->lazyIdentities()->classOf('@decor:app.window:base'));
        self::assertSame(
            ['app.first' => HeavyEngine::class, 'app.second' => HeavyEngine::class],
            $inventory->lazy(),
        );
    }

    public function testTheArtifactLaneAnswersTheSameInventory(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        $second = $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        $outcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::ArtifactLoaded, $outcome->lane);

        $inventory = $this->inventory($second);

        self::assertSame(
            ['app.window', 'app.house', 'app.short', 'app.engine', 'app.fuel', 'app.answer'],
            $inventory->ids(),
            'the parsed declarations answer the same inventory whichever lane booted',
        );
        $engine = $inventory->entry('app.engine');
        self::assertNotNull($engine);
        self::assertSame('factory', $engine->shape);
        self::assertSame(['app.fuel'], $engine->dependencies);
    }

    /**
     * The booted container's own inventory, typed for the read side
     * the assertions walk.
     */
    private function inventory(Container $container): ContainerInventory
    {
        $inventory = $container->get(FrameworkDefaults::INVENTORY_SERVICE);
        self::assertInstanceOf(ContainerInventory::class, $inventory);

        return $inventory;
    }

    private function booted(): Container
    {
        return new ContainerBootstrapper()->bootstrap(new Config($this->config()), new Container());
    }

    /**
     * The governed configuration the inventory reads: one of every
     * shape, a declared dependency, a tag, a method-call injection --
     * the recipe the entries answer.
     *
     * @return array{services: array<string, array<string, mixed>>}
     */
    private function config(): array
    {
        return [
            'services' => [
                'app.window' => [
                    'class' => NamespacedService::class,
                    'arguments' => ['win'],
                    'tags' => ['frame.window'],
                ],
                'app.house' => [
                    'class' => NamespacedWrapper::class,
                    'arguments' => ['house', ['service' => 'app.window']],
                ],
                'app.short' => ['alias' => 'app.window'],
                'app.engine' => [
                    'factory' => EngineFactory::class . '::make',
                    'arguments' => [['service' => 'app.fuel']],
                    'calls' => [['method' => 'thrust']],
                ],
                'app.fuel' => [
                    'class' => EngineFuel::class,
                    'calls' => [['method' => 'burn'], ['method' => 'burn']],
                ],
                'app.answer' => ['value' => 42],
            ],
        ];
    }

    /**
     * @return array{aot: array{enabled: bool, warm: bool}, services: array<string, array<string, mixed>>}
     */
    private function aotConfig(): array
    {
        return ['aot' => ['enabled' => true, 'warm' => false], ...$this->config()];
    }
}
