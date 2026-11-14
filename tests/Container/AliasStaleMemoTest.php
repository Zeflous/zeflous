<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\AliasRides;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerInstalls;
use Zef\Framework\Container\RebindInvalidations;
use Zef\Framework\Container\ServiceStore;
use Zef\Framework\Container\SingletonMemo;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Greeter;
use Zef\Test\Support\Container\Lazy\EngineFuel;
use Zef\Test\Support\Container\Lazy\HeavyEngine;

/**
 * The stale-memo symptom at the boot's own surface (#770's evidence,
 * closed): what `get(alias)` answers after the target is re-registered
 * -- the new registration's instance, never the stale one the alias's
 * own memo slot pinned, through the whole chain and through both
 * install lanes.
 *
 * The contract under test is the singleton memo's own ({@see
 * SingletonMemo}): "a re-registration's invalidation -- the previous
 * instance must never answer again", reached through the alias lane
 * the way the grammar's transparency claim reaches it: an alias is the
 * target's identity by another name, so the rebind owes it the same
 * freshness the target's own lane gets.
 *
 * @internal
 */
#[CoversClass(AliasRides::class)]
#[CoversClass(RebindInvalidations::class)]
#[CoversClass(ServiceStore::class)]
#[CoversClass(SingletonMemo::class)]
#[CoversClass(ContainerInstalls::class)]
#[CoversClass(Container::class)]
#[CoversClass(ContainerBootstrapper::class)]
final class AliasStaleMemoTest extends TestCase
{
    public function testAReRegisteredTargetReachesItsAliasWithTheNewInstance(): void
    {
        // The issue's own evidence, closed: after `set('app.greeter',
        // factoryBaru)` the alias must answer the instance the new
        // factory builds -- the same one the target's own lane
        // answers, never the stale one the alias's memo slot pinned.
        $container = $this->booted([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']],
            'app.greeter.alias' => ['alias' => 'app.greeter'],
        ]);

        $stale = $container->get('app.greeter.alias');
        self::assertSame($stale, $container->get('app.greeter'));

        $container->set('app.greeter', static fn (): Greeter => new Greeter('Reborn'));

        $freshTarget = $container->get('app.greeter');
        $freshAlias = $container->get('app.greeter.alias');

        self::assertInstanceOf(Greeter::class, $freshAlias);
        self::assertSame($freshTarget, $freshAlias);
        self::assertNotSame($stale, $freshAlias);
        self::assertSame('Reborn', $freshAlias->salutation);
    }

    public function testAReRegisteredTargetReachesItsWholeChain(): void
    {
        // `top -> mid -> base`: rebinding the base must refresh the
        // mid and the top both -- a rider's rider is exactly as stale
        // as the rider.
        $container = $this->booted([
            'app.base' => ['class' => Greeter::class, 'arguments' => ['Base']],
            'app.mid' => ['alias' => 'app.base'],
            'app.top' => ['alias' => 'app.mid'],
        ]);

        $staleTop = $container->get('app.top');
        $staleMid = $container->get('app.mid');

        $container->set('app.base', static fn (): Greeter => new Greeter('Reborn'));

        self::assertNotSame($staleTop, $container->get('app.top'));
        self::assertNotSame($staleMid, $container->get('app.mid'));
        self::assertSame($container->get('app.base'), $container->get('app.top'));
        self::assertSame($container->get('app.base'), $container->get('app.mid'));
    }

    public function testAReRegisteredMiddleAliasAnswersItsOwnFactoryAndRefreshesItsRider(): void
    {
        // Re-registering the *mid* id: it answers its own new factory
        // now (a real service), and the top riding it must follow the
        // new answer -- while the base's own lane keeps its instance.
        $container = $this->booted([
            'app.base' => ['class' => Greeter::class, 'arguments' => ['Base']],
            'app.mid' => ['alias' => 'app.base'],
            'app.top' => ['alias' => 'app.mid'],
        ]);

        $baseInstance = $container->get('app.base');
        $container->get('app.top');

        $container->set('app.mid', static fn (): Greeter => new Greeter('MidReborn'));

        $midReborn = $container->get('app.mid');
        $topReborn = $container->get('app.top');

        self::assertInstanceOf(Greeter::class, $midReborn);
        self::assertInstanceOf(Greeter::class, $topReborn);
        self::assertSame('MidReborn', $midReborn->salutation);
        self::assertSame('MidReborn', $topReborn->salutation);
        self::assertSame($baseInstance, $container->get('app.base'));
    }

    public function testAReRegisteredAliasIdStopsRidingAndKeepsItsOwnInstance(): void
    {
        // The alias id itself re-registered: it answers its own
        // factory now, and a *later* rebind of the old target must
        // not reach through it -- the dropped mark is the identity
        // boundary the registry owes.
        $container = $this->booted([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']],
            'app.greeter.alias' => ['alias' => 'app.greeter'],
        ]);

        $container->set('app.greeter.alias', static fn (): Greeter => new Greeter('Own'));

        $ownInstance = $container->get('app.greeter.alias');

        $container->set('app.greeter', static fn (): Greeter => new Greeter('Reborn'));

        $reboundTarget = $container->get('app.greeter');

        self::assertInstanceOf(Greeter::class, $reboundTarget);
        self::assertSame($ownInstance, $container->get('app.greeter.alias'));
        self::assertSame('Reborn', $reboundTarget->salutation);
    }

    public function testATransientTargetsAliasStaysFreshAcrossARebind(): void
    {
        // The no-memo lane: a transient-aligned alias never pins an
        // instance anywhere, so the rebind owes it nothing new -- the
        // pin is that nothing the registry does regresses it, and
        // that the per-call delegation answers the target's *current*
        // registration: the rebind defaults to the singleton lane,
        // so the alias's delegation answers the target's memoised
        // instance, exactly as the target's own lane does.
        $container = $this->booted([
            'app.work' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'lifetime' => 'transient'],
            'app.work.alias' => ['alias' => 'app.work'],
        ]);

        $container->set('app.work', static fn (): Greeter => new Greeter('Reborn'));

        $reboundAlias = $container->get('app.work.alias');

        self::assertInstanceOf(Greeter::class, $reboundAlias);
        self::assertSame('Reborn', $reboundAlias->salutation);
        self::assertSame($container->get('app.work'), $reboundAlias);
    }

    public function testARequestScopedAliasAnswersTheReboundTargetInsideANewScope(): void
    {
        // The scope lane: a rebind the scope machinery consults fresh
        // -- a new scope's slot fill delegates through the target's
        // *current* registration, so the rebind reaches the alias
        // lane the moment a scope opens on it.
        $container = $this->booted([
            'app.leaf' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'lifetime' => 'request'],
            'app.leaf.alias' => ['alias' => 'app.leaf'],
        ]);

        $firstScope = $container->createRequestScope();
        $firstScope->get('app.leaf.alias');

        $container->set('app.leaf', static fn (): Greeter => new Greeter('Reborn'));

        $secondScope = $container->createRequestScope();
        $reboundLeaf = $secondScope->get('app.leaf.alias');

        self::assertInstanceOf(Greeter::class, $reboundLeaf);
        self::assertSame('Reborn', $reboundLeaf->salutation);
    }

    public function testAReRegisteredTargetForgetsItsLazyMarkThroughTheWalk(): void
    {
        // The walk's own lazy arm, pinned here because the walk owns
        // that forget now: the re-registered factory answers eagerly,
        // the declaration's deferral mark gone with the memo.
        $container = $this->booted([
            'app.fuel' => ['class' => EngineFuel::class],
            'app.engine' => [
                'class' => HeavyEngine::class,
                'arguments' => [['service' => 'app.fuel'], 7],
                'lazy' => true,
            ],
        ]);

        HeavyEngine::$constructions = 0;

        $container->set('app.engine', static fn (): HeavyEngine => new HeavyEngine(new EngineFuel()));

        $engine = $container->get('app.engine');

        self::assertInstanceOf(HeavyEngine::class, $engine);
        self::assertSame(1, HeavyEngine::$constructions, 'the re-registered factory runs eagerly');
    }

    /**
     * @param array<string, array<string, mixed>> $services
     */
    private function booted(array $services): Container
    {
        return new ContainerBootstrapper()->bootstrap(
            new Config(['services' => $services]),
            new Container(),
        );
    }
}
