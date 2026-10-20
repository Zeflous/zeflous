<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerLocator;
use Zef\Framework\Container\LocatorAsks;
use Zef\Framework\Container\LocatorRefusals;
use Zef\Framework\Container\NotFoundException;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Lazy\EngineFuel;
use Zef\Test\Support\Container\Lazy\HeavyEngine;
use Zef\Test\Support\Container\Namespaces\FallbackFactories;
use Zef\Test\Support\Container\Namespaces\NamespacedService;
use Zef\Test\Support\Container\Namespaces\NamespacedWrapper;

/**
 * The subset locator's own grammar and behaviour (ROADMAP area 10's
 * service locator): the fail-closed construction, the boundary the
 * subset enforces, the delegation that rides the container's own
 * lanes, and the PSR-11 conformance of both faces.
 *
 * @internal
 */
#[CoversClass(ContainerLocator::class)]
#[CoversClass(LocatorAsks::class)]
#[CoversClass(LocatorRefusals::class)]
final class ContainerLocatorTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        HeavyEngine::reset();
        EngineFuel::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        HeavyEngine::reset();
        EngineFuel::reset();
    }

    public function testTheLocatorDelegatesResolutionToTheContainersLanes(): void
    {
        $container = new Container();
        $container->set('app.answer', static fn (): int => 42);
        $container->set('app.word', static fn (): string => 'hello');

        $locator = $container->locator('app.answer', 'app.word');

        self::assertSame(42, $locator->get('app.answer'));
        self::assertSame('hello', $locator->get('app.word'));

        // The delegation rides the container's own memoisation: the
        // singleton the locator answers is the singleton it keeps.
        self::assertSame($locator->get('app.answer'), $container->get('app.answer'));
    }

    public function testTheSubsetAnswersInAskOrderWithRepeatsCollapsed(): void
    {
        $container = $this->booted();
        $locator = $container->locator('app.house', 'app.window', 'app.house');

        self::assertSame(['app.house', 'app.window'], $locator->ids());
    }

    public function testTheAskAnswersTheMembershipMapItCarries(): void
    {
        $container = new Container();
        $container->set('app.answer', static fn (): int => 42);
        $container->set('app.word', static fn (): string => 'hello');

        // The subset's membership is the ask's own answer: one true
        // member per asked identifier, repeats collapsed into the
        // first ask's position -- the map the locator's faces read.
        self::assertSame(
            ['app.answer' => true, 'app.word' => true],
            LocatorAsks::validated($container, 'app.answer', 'app.word', 'app.answer'),
        );
    }

    public function testTheBoundaryRefusesIdentifiersOutsideTheSubset(): void
    {
        $container = $this->booted();
        $locator = $container->locator('app.window');

        // The container itself answers the identifier; the view does
        // not -- the restriction is the point of the subset, enforced.
        self::assertTrue($container->has('app.house'));
        self::assertFalse($locator->has('app.house'));

        try {
            $locator->get('app.house');
            self::fail('Expected a NotFoundException to be thrown.');
        } catch (NotFoundException $notFoundException) {
            self::assertSame(
                'Service "app.house" is not in this locator\'s subset '
                . '(the subset is fixed at construction -- ask the container, or build the locator with it).',
                $notFoundException->getMessage(),
            );
        }
    }

    public function testTheConstructionRefusesTheIdentifiersItCannotCarry(): void
    {
        $container = $this->booted();

        try {
            $container->locator('app.window', '');
            self::fail('Expected a NotFoundException to be thrown.');
        } catch (NotFoundException $notFoundException) {
            self::assertSame(
                'A locator identifier must be a non-empty service identifier.',
                $notFoundException->getMessage(),
            );
        }

        try {
            $container->locator('app.absent');
            self::fail('Expected a NotFoundException to be thrown.');
        } catch (NotFoundException $notFoundException) {
            self::assertSame(
                "The identifier 'app.absent' is not carried by the container "
                . "(a locator's subset names identifiers the container can already answer).",
                $notFoundException->getMessage(),
            );
        }

        try {
            $container->locator('@decor:app.target:base');
            self::fail('Expected a NotFoundException to be thrown.');
        } catch (NotFoundException $notFoundException) {
            self::assertSame(
                "The identifier '@decor:app.target:base' is the boot's own machinery and not a host service "
                . '(a locator hands over services, not the machinery that builds them).',
                $notFoundException->getMessage(),
            );
        }
    }

    public function testAFallbackCoveredIdentifierIsCarriedAndResolved(): void
    {
        $container = $this->booted();
        $container->namespaceFallbacks()->fallback('App\Controllers\\', FallbackFactories::forIdentifier(...));

        // `has()` answers the covering fallback, so the ask passes the
        // construction gate -- and the resolution rides the same lane.
        $locator = $container->locator('App\Controllers\HomeController');

        self::assertTrue($locator->has('App\Controllers\HomeController'));
        $controller = $locator->get('App\Controllers\HomeController');
        self::assertInstanceOf(NamespacedService::class, $controller);
        self::assertSame('App\Controllers\HomeController', $controller->label);
    }

    public function testALazyMarkAnswersItsProxyThroughTheView(): void
    {
        $container = new Container();
        $container->set(
            'app.engine',
            static fn (): HeavyEngine => new HeavyEngine(new EngineFuel(), 5),
        );
        $container->markLazy('app.engine', HeavyEngine::class);

        $locator = $container->locator('app.engine');
        $engine = $locator->get('app.engine');

        self::assertInstanceOf(HeavyEngine::class, $engine);
        self::assertSame(0, HeavyEngine::$constructions, 'the deferral rides the delegation');
        self::assertSame(5, $engine->thrust());
        self::assertSame(1, HeavyEngine::$constructions);
    }

    /**
     * The governed configuration the booted cases share: a window, a
     * house that references it, and a decorated target -- the shapes
     * the subset asks over.
     */
    private function booted(): Container
    {
        return new ContainerBootstrapper()->bootstrap(new Config([
            'services' => [
                'app.window' => ['class' => NamespacedService::class, 'arguments' => ['win']],
                'app.house' => ['class' => NamespacedService::class, 'arguments' => [['service' => 'app.window']]],
                'app.target' => ['class' => NamespacedService::class, 'arguments' => ['inner']],
            ],
            'decorators' => [
                'app.target' => [
                    [
                        'class' => NamespacedWrapper::class,
                        'arguments' => ['wrapped', ['service' => '@inner']],
                    ],
                ],
            ],
        ]), new Container());
    }
}
