<?php

declare(strict_types=1);

namespace Zef\Test;

use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use stdClass;
use Zef\Framework\Container\CircularDependencyException;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\CycleGuard;
use Zef\Framework\Container\FactoryRegistrations;
use Zef\Framework\Container\NotFoundException;
use Zef\Framework\Container\ResolutionDepthException;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceResolutions;
use Zef\Framework\Container\ServiceStore;
use Zef\Framework\Container\SingletonMemo;
use Zef\Test\Support\Container\ScopedBranch;

/**
 * @internal
 */
#[CoversClass(CycleGuard::class)]
#[CoversClass(FactoryRegistrations::class)]
#[CoversClass(ServiceResolutions::class)]
#[CoversClass(ServiceStore::class)]
#[CoversClass(SingletonMemo::class)]
final class ServiceStoreTest extends TestCase
{
    public function testHasIsFalseForAnUnknownIdentifier(): void
    {
        self::assertFalse(new ServiceStore()->has('unknown'));
    }

    public function testHasIsTrueAfterSet(): void
    {
        $serviceStore = new ServiceStore();
        $serviceStore->set('service', static fn (): string => 'value');

        self::assertTrue($serviceStore->has('service'));
    }

    public function testResolveBuildsTheFactoryResult(): void
    {
        $serviceStore = new ServiceStore();
        $serviceStore->set('service', static fn (): string => 'value');

        self::assertSame('value', $serviceStore->resolve('service', new Container()));
    }

    public function testResolveMemoisesTheInstance(): void
    {
        $serviceStore = new ServiceStore();
        $serviceStore->set('service', static fn (): object => new stdClass());

        $container = new Container();

        self::assertSame($serviceStore->resolve('service', $container), $serviceStore->resolve('service', $container));
    }

    public function testResolveReturnsAMemoisedNullWithoutRebuilding(): void
    {
        $serviceStore = new ServiceStore();
        $calls = 0;
        $serviceStore->set('service', static function () use (&$calls): mixed {
            ++$calls;

            return null;
        });
        $container = new Container();

        self::assertNull($serviceStore->resolve('service', $container));
        self::assertNull($serviceStore->resolve('service', $container));
        self::assertSame(1, $calls);
    }

    public function testSetInvalidatesAPreviouslyResolvedInstance(): void
    {
        $serviceStore = new ServiceStore();
        $serviceStore->set('service', static fn (): string => 'first');
        $serviceStore->resolve('service', new Container());
        $serviceStore->set('service', static fn (): string => 'second');

        self::assertSame('second', $serviceStore->resolve('service', new Container()));
    }

    public function testResolveThrowsForAnUnknownIdentifier(): void
    {
        $this->expectException(NotFoundException::class);

        new Container()->get('unknown');
    }

    public function testATwoServiceCycleIsRefusedWithTheChain(): void
    {
        $container = new Container();
        $container->set('app.a', static function (Container $container): string {
            $container->get('app.b');

            return 'a';
        });
        $container->set('app.b', static function (Container $container): string {
            $container->get('app.a');

            return 'b';
        });

        try {
            $container->get('app.a');
            self::fail('A circular factory pair must be refused, not recursed into.');
        } catch (CircularDependencyException $circularDependencyException) {
            self::assertSame(
                "Service 'app.a' is part of a circular reference chain: app.a -> app.b -> app.a.",
                $circularDependencyException->getMessage(),
            );
        }
    }

    public function testAnIndirectCycleIsRefusedWithTheWalkedChain(): void
    {
        // The same three-definition walk the compile-time graph rejects
        // (ServiceDefinitionGraphTest::testAnIndirectCycleIsRejectedWithTheWalkedChain),
        // refused at runtime with the same message shape: the two guards
        // are the same discipline at the two times a cycle can surface.
        $container = new Container();
        $container->set('app.a', static function (Container $container): string {
            $container->get('app.b');

            return 'a';
        });
        $container->set('app.b', static function (Container $container): string {
            $container->get('app.c');

            return 'b';
        });
        $container->set('app.c', static function (Container $container): string {
            $container->get('app.a');

            return 'c';
        });

        try {
            $container->get('app.a');
            self::fail('An indirect circular chain must be refused, not recursed into.');
        } catch (CircularDependencyException $circularDependencyException) {
            self::assertSame(
                "Service 'app.a' is part of a circular reference chain: app.a -> app.b -> app.c -> app.a.",
                $circularDependencyException->getMessage(),
            );
        }
    }

    public function testADeeperCycleEnteredFromOutsideAnchorsAtTheClosingService(): void
    {
        // The cycle is b -> c -> b; the caller asked for x. The refusal
        // anchors at the id that closes the chain -- the defective
        // definition -- exactly as the compile-time walk over the same
        // graph would report it, with the honest walk from the entry.
        $container = new Container();
        $container->set('app.x', static function (Container $container): string {
            $container->get('app.b');

            return 'x';
        });
        $container->set('app.b', static function (Container $container): string {
            $container->get('app.c');

            return 'b';
        });
        $container->set('app.c', static function (Container $container): string {
            $container->get('app.b');

            return 'c';
        });

        try {
            $container->get('app.x');
            self::fail('The deeper circular chain must be refused.');
        } catch (CircularDependencyException $circularDependencyException) {
            self::assertSame(
                "Service 'app.b' is part of a circular reference chain: app.x -> app.b -> app.c -> app.b.",
                $circularDependencyException->getMessage(),
            );
        }
    }

    public function testASelfReferencingServiceIsRefusedWithItsOwnChain(): void
    {
        $container = new Container();
        $container->set('app.a', static function (Container $container): string {
            $container->get('app.a');

            return 'a';
        });

        try {
            $container->get('app.a');
            self::fail('A self-referencing factory must be refused, not recursed into.');
        } catch (CircularDependencyException $circularDependencyException) {
            self::assertSame(
                "Service 'app.a' is part of a circular reference chain: app.a -> app.a.",
                $circularDependencyException->getMessage(),
            );
        }
    }

    public function testTheCycleRefusalIsThePsr11ContainerExceptionTheCallerCatches(): void
    {
        // PSR-11's own contract: a resolution failure is an exception
        // the caller can catch -- never a fatal the process cannot.
        // The refusal must surface through the container's own
        // exception interface, not a subclass callers cannot name.
        $container = new Container();
        $container->set('app.a', static function (Container $container): string {
            $container->get('app.a');

            return 'a';
        });

        try {
            $container->get('app.a');
            self::fail('The cycle must be refused.');
        } catch (ContainerExceptionInterface $containerException) {
            self::assertSame(
                "Service 'app.a' is part of a circular reference chain: app.a -> app.a.",
                $containerException->getMessage(),
            );
        }
    }

    public function testASharedDependencyIsNotACycle(): void
    {
        // The finally clears the stack when a factory returns: two
        // services resolving the same shared leaf must both answer,
        // and the leaf must be memoised across them -- a diamond is
        // not a cycle. The leaf is an object so the memoisation is
        // proven by identity, not by value equality.
        $leaf = new stdClass();
        $container = new Container();
        $container->set('app.leaf', static fn (): stdClass => $leaf);
        $container->set('app.left', static fn (Container $container): mixed => $container->get('app.leaf'));
        $container->set('app.right', static fn (Container $container): mixed => $container->get('app.leaf'));

        self::assertSame($leaf, $container->get('app.left'));
        self::assertSame($leaf, $container->get('app.right'));
    }

    public function testARefusedCycleLeavesNoResidueOnTheStack(): void
    {
        // The finally pops the refused frame: a later honest
        // resolution of the same ids must answer, so the guard can
        // never poison the store it protects.
        $container = new Container();
        $container->set('app.a', static function (Container $container): string {
            $container->get('app.b');

            return 'a';
        });
        $container->set('app.b', static function (Container $container): string {
            $container->get('app.a');

            return 'b';
        });

        try {
            $container->get('app.a');
            self::fail('The circular pair must be refused.');
        } catch (CircularDependencyException) {
            self::addToAssertionCount(1);
        }

        $container->set('app.b', static fn (): string => 'b-honest');

        self::assertSame('a', $container->get('app.a'), 'the honest re-resolution answers after the refusal');
    }

    public function testATransientServiceBuildsFreshOnEveryResolution(): void
    {
        $container = new Container();
        $container->set('app.fresh', static fn (): stdClass => new stdClass(), ServiceLifetime::TRANSIENT);

        self::assertNotSame($container->get('app.fresh'), $container->get('app.fresh'));
    }

    public function testATransientServiceStillRidesTheCycleGuard(): void
    {
        $container = new Container();
        $container->set(
            'app.selfRef',
            static function (Container $container): ScopedBranch {
                $leaf = $container->get('app.selfRef');
                Assert::assertInstanceOf(stdClass::class, $leaf, 'the self reference is the branch factory answer');

                return new ScopedBranch($leaf);
            },
            ServiceLifetime::TRANSIENT,
        );

        $this->expectException(CircularDependencyException::class);
        $container->get('app.selfRef');
    }

    public function testAReplacedDefinitionCarriesItsNewLifetime(): void
    {
        $container = new Container();
        $container->set('app.thing', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);
        $container->set('app.thing', static fn (): stdClass => new stdClass(), ServiceLifetime::TRANSIENT);

        self::assertNotSame($container->get('app.thing'), $container->get('app.thing'));
    }

    public function testAChainDeeperThanTheBudgetIsRefusedWithTheBudget(): void
    {
        // limit 1: one open frame at a time -- the second frame of a
        // two-long chain is already deeper than the budget, refused
        // with the budget in the message.
        $cycleGuard = new CycleGuard(1);

        try {
            $cycleGuard->resolve(
                'app.a',
                static fn (Container $container): mixed => $cycleGuard->resolve(
                    'app.b',
                    static fn (): string => 'b',
                    $container,
                ),
                new Container(),
            );
            self::fail('Expected a ResolutionDepthException to be thrown.');
        } catch (ResolutionDepthException $resolutionDepthException) {
            self::assertSame(
                "Service 'app.b' resolves through a chain deeper than the budget of 1 open frames "
                . '-- the declaration graph is pathological.',
                $resolutionDepthException->getMessage(),
            );
        }
    }

    public function testAChainExactlyAtTheBudgetResolves(): void
    {
        $cycleGuard = new CycleGuard(2);

        $resolved = $cycleGuard->resolve(
            'app.a',
            static fn (Container $container): mixed => $cycleGuard->resolve(
                'app.b',
                static fn (): string => 'b',
                $container,
            ),
            new Container(),
        );
        \assert(\is_string($resolved), 'the leaf factory answers a string');

        self::assertSame('b', $resolved);
    }

    public function testTheDefaultBudgetHoldsA256FrameChain(): void
    {
        // 255 chained factories plus the leaf: 256 frames open at the
        // deepest point -- exactly the budget, and it resolves.
        $container = new Container();
        for ($depth = 0; $depth <= 254; ++$depth) {
            $container->set(
                'app.depth.' . $depth,
                static fn (Container $container): mixed => $container->get('app.depth.' . ($depth + 1)),
            );
        }

        $container->set('app.depth.255', static fn (): string => 'leaf');

        self::assertSame('leaf', $container->get('app.depth.0'));
    }

    public function testTheDefaultBudgetRefusesA257FrameChain(): void
    {
        // 256 chained factories plus the leaf: the 257th frame is one
        // past the default budget, and the refusal carries the budget
        // itself -- the exact boundary. A deeper chain (the 258-frame
        // one below) refuses under a loosened budget too, so only this
        // frame count pins the default at 256.
        $container = new Container();
        for ($depth = 0; $depth <= 255; ++$depth) {
            $container->set(
                'app.depth.' . $depth,
                static fn (Container $container): mixed => $container->get('app.depth.' . ($depth + 1)),
            );
        }

        $container->set('app.depth.256', static fn (): string => 'leaf');

        try {
            $container->get('app.depth.0');
            self::fail('Expected a ResolutionDepthException to be thrown.');
        } catch (ResolutionDepthException $resolutionDepthException) {
            self::assertSame(
                "Service 'app.depth.256' resolves through a chain deeper than the budget of 256 open frames "
                . '-- the declaration graph is pathological.',
                $resolutionDepthException->getMessage(),
            );
        }
    }

    public function testTheDefaultBudgetRefusesA258FrameChain(): void
    {
        $container = new Container();
        for ($depth = 0; $depth <= 256; ++$depth) {
            $container->set(
                'app.depth.' . $depth,
                static fn (Container $container): mixed => $container->get('app.depth.' . ($depth + 1)),
            );
        }

        $container->set('app.depth.257', static fn (): string => 'leaf');

        $this->expectException(ResolutionDepthException::class);
        $container->get('app.depth.0');
    }

    public function testADepthBudgetBelowOneIsRefusedAtConstruction(): void
    {
        try {
            new CycleGuard(0);
            self::fail('Expected an InvalidArgumentException to be thrown.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The resolution depth limit must be at least 1.',
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
