<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use stdClass;
use Zef\Framework\Container\ActiveScope;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\RequestInstances;
use Zef\Framework\Container\RequestScope;
use Zef\Framework\Container\RequestScopeException;
use Zef\Framework\Container\RequestScopeRefusals;
use Zef\Framework\Container\ResolutionFrames;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceResolutions;
use Zef\Framework\Container\ServiceStore;
use Zef\Test\Support\Container\CaptiveHolder;
use Zef\Test\Support\Container\NestedHolder;
use Zef\Test\Support\Container\ScopedBranch;

/**
 * @internal
 */
#[CoversClass(ActiveScope::class)]
#[CoversClass(RequestInstances::class)]
#[CoversClass(RequestScope::class)]
#[CoversClass(RequestScopeRefusals::class)]
#[CoversClass(ResolutionFrames::class)]
#[CoversClass(ServiceResolutions::class)]
#[CoversClass(ServiceStore::class)]
final class RequestScopeTest extends TestCase
{
    public function testARequestScopedServiceMemoisesInsideOneScope(): void
    {
        $container = new Container();
        $container->set('app.thing', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        $scope = $container->createRequestScope();

        self::assertSame($scope->get('app.thing'), $scope->get('app.thing'));
    }

    public function testEachScopeObservesItsOwnInstance(): void
    {
        $container = new Container();
        $container->set('app.thing', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        $firstScope = $container->createRequestScope();
        $secondScope = $container->createRequestScope();

        self::assertNotSame($firstScope->get('app.thing'), $secondScope->get('app.thing'));
    }

    public function testTheRootRefusesARequestScopedResolutionWithTheWayOut(): void
    {
        $container = new Container();
        $container->set('app.thing', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        try {
            $container->get('app.thing');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertSame(
                "Service 'app.thing' is request-scoped and cannot be resolved outside a request scope "
                . '-- open one with the container and resolve through it.',
                $requestScopeException->getMessage(),
            );
        }
    }

    public function testTheRootRefusesARequestScopedResolutionAfterAScopeResolved(): void
    {
        // The scope is resolution state, not a mode the container enters:
        // once the scoped resolution returns, the root is unscoped again
        // -- a later root resolution of a request-scoped id is the
        // outside-scope refusal, never a silent ride into the scope an
        // earlier resolution opened.
        $container = new Container();
        $container->set('app.thing', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        $scope = $container->createRequestScope();
        self::assertInstanceOf(stdClass::class, $scope->get('app.thing'));

        try {
            $container->get('app.thing');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertSame(
                "Service 'app.thing' is request-scoped and cannot be resolved outside a request scope "
                . '-- open one with the container and resolve through it.',
                $requestScopeException->getMessage(),
            );
        }
    }

    public function testAnInnerScopeResolutionRestoresTheOuterScopeForWhatFollows(): void
    {
        // A factory may open a scope of its own mid-resolution: the inner
        // scope's resolutions land in the inner scope, and the resolution
        // that opened it resumes with the OUTER scope open again -- the
        // fellow request-scoped dependency it resolves afterwards lands
        // in the outer scope, never in the finished inner one.
        $container = new Container();
        $container->set('app.leaf', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        $outerScope = $container->createRequestScope();
        $innerScope = $container->createRequestScope();
        $container->set(
            'app.branch',
            static function (Container $container) use ($innerScope): array {
                $innerLeaf = $innerScope->get('app.leaf');
                Assert::assertInstanceOf(stdClass::class, $innerLeaf, 'the inner scope answers its own instance');

                $outerLeaf = $container->get('app.leaf');
                Assert::assertInstanceOf(stdClass::class, $outerLeaf, 'the outer resolution answers its own');

                return [$innerLeaf, $outerLeaf];
            },
            ServiceLifetime::REQUEST,
        );

        $pair = $outerScope->get('app.branch');
        self::assertIsArray($pair, 'the branch factory answers its two leaves as a pair');
        $innerLeaf = $pair[0];
        $outerLeaf = $pair[1];
        self::assertInstanceOf(stdClass::class, $innerLeaf);
        self::assertInstanceOf(stdClass::class, $outerLeaf);
        self::assertNotSame($innerLeaf, $outerLeaf, 'the two scopes never share an instance');
        self::assertSame($innerLeaf, $innerScope->get('app.leaf'), 'the inner scope memoised its own instance');
        self::assertSame($outerLeaf, $outerScope->get('app.leaf'), 'the outer scope memoised its own instance');
    }

    public function testASingletonResolvesIdenticallyThroughAScope(): void
    {
        $container = new Container();
        $container->set('app.shared', static fn (): stdClass => new stdClass());

        $scope = $container->createRequestScope();

        self::assertSame($container->get('app.shared'), $scope->get('app.shared'));
    }

    public function testATransientBuildsFreshInsideTheScope(): void
    {
        $container = new Container();
        $container->set('app.fresh', static fn (): stdClass => new stdClass(), ServiceLifetime::TRANSIENT);

        $scope = $container->createRequestScope();

        self::assertNotSame($scope->get('app.fresh'), $scope->get('app.fresh'));
    }

    public function testAFellowRequestScopedDependencyLandsInTheSameScope(): void
    {
        $container = new Container();
        $container->set('app.leaf', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);
        $container->set(
            'app.branch',
            static function (Container $container): ScopedBranch {
                $leaf = $container->get('app.leaf');
                Assert::assertInstanceOf(stdClass::class, $leaf, 'the leaf is the stdClass factory answer');

                return new ScopedBranch($leaf);
            },
            ServiceLifetime::REQUEST,
        );
        $scope = $container->createRequestScope();

        $branch = $scope->get('app.branch');
        self::assertInstanceOf(ScopedBranch::class, $branch);
        self::assertSame($scope->get('app.leaf'), $branch->leaf);
        self::assertNotSame($container->createRequestScope()->get('app.leaf'), $branch->leaf);
    }

    public function testAClosedScopeRefusesEveryFurtherResolution(): void
    {
        $container = new Container();
        $container->set('app.thing', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        $scope = $container->createRequestScope();
        $scope->close();

        self::assertTrue($scope->isClosed());

        try {
            $scope->get('app.thing');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertSame(
                'The request scope is closed -- the request it named is over; '
                . "service 'app.thing' cannot be resolved through it.",
                $requestScopeException->getMessage(),
            );
        }
    }

    public function testAClosedScopeAnswersHasForNothing(): void
    {
        $container = new Container();
        $container->set('app.thing', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        $scope = $container->createRequestScope();

        self::assertTrue($scope->has('app.thing'));
        $scope->close();
        self::assertFalse($scope->has('app.thing'));
    }

    public function testAScopeMayCloseTwice(): void
    {
        $container = new Container();
        $scope = $container->createRequestScope();
        $scope->close();
        $scope->close();

        self::assertTrue($scope->isClosed());
    }

    public function testANewScopeIsIndependentOfAClosedOne(): void
    {
        $container = new Container();
        $container->set('app.thing', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        $firstScope = $container->createRequestScope();
        $firstScope->close();

        $secondScope = $container->createRequestScope();
        self::assertInstanceOf(stdClass::class, $secondScope->get('app.thing'));
    }

    public function testClosingAScopeDoesNotTouchTheSingletonsItBuilt(): void
    {
        $container = new Container();
        $container->set('app.shared', static fn (): stdClass => new stdClass());

        $scope = $container->createRequestScope();
        $shared = $scope->get('app.shared');
        $scope->close();

        self::assertSame($shared, $container->get('app.shared'));
    }

    public function testAFactoryClosingItsOwnScopeRefusesItsOwnCompletion(): void
    {
        $container = new Container();
        $scope = $container->createRequestScope();
        $container->set(
            'app.selfCloser',
            static function (Container $container) use ($scope): stdClass {
                $scope->close();

                return new stdClass();
            },
            ServiceLifetime::REQUEST,
        );

        try {
            $scope->get('app.selfCloser');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertSame(
                "Service 'app.selfCloser' resolves through a request scope that was closed mid-resolution "
                . '(a factory closed the scope its own resolution still runs in).',
                $requestScopeException->getMessage(),
            );
        }
    }

    public function testAScopeClosedMidResolutionRefusesTheRemainingRequestResolutions(): void
    {
        $container = new Container();
        $leafBuilds = 0;
        $container->set(
            'app.leaf',
            static function () use (&$leafBuilds): stdClass {
                ++$leafBuilds;

                return new stdClass();
            },
            ServiceLifetime::REQUEST,
        );
        $scope = $container->createRequestScope();
        $container->set(
            'app.selfCloser',
            static function (Container $container) use ($scope): stdClass {
                $scope->close();

                $leaf = $container->get('app.leaf');
                Assert::assertInstanceOf(
                    stdClass::class,
                    $leaf,
                    'unreachable: the closed scope refuses the leaf first',
                );

                return $leaf;
            },
            ServiceLifetime::REQUEST,
        );

        try {
            $scope->get('app.selfCloser');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertSame(
                "Service 'app.leaf' resolves through a request scope that was closed mid-resolution "
                . '(a factory closed the scope its own resolution still runs in).',
                $requestScopeException->getMessage(),
            );
        }

        // The early refusal fires before the leaf's factory runs -- the
        // closed scope never re-runs a factory only to refuse its result.
        self::assertSame(0, $leafBuilds);
    }

    public function testTheScopeRefusalsAreThePsr11ContainerExceptionTheCallerCatches(): void
    {
        // PSR-11's own contract: a resolution failure is an exception the
        // caller can catch -- the refusal surfaces through the container's
        // own exception interface, never a fatal the process cannot.
        $container = new Container();
        $container->set('app.thing', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);

        try {
            $container->get('app.thing');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (ContainerExceptionInterface $containerException) {
            self::assertStringContainsString(
                "Service 'app.thing' is request-scoped and cannot be resolved outside a request scope",
                $containerException->getMessage(),
            );
        }
    }

    public function testASingletonResolvingARequestScopedIdIsTheCaptiveRefusal(): void
    {
        // The runtime capture the boot graph refuses for declared
        // definitions, answered here for the programmatic path: the
        // singleton's forever memo must never hold an instance born in
        // a request scope.
        $container = new Container();
        $container->set('app.leaf', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);
        // The captor factory resolves the request-scoped leaf while its
        // own singleton frame is open.
        $container->set(
            'app.captor',
            static function (Container $container): CaptiveHolder {
                $leaf = $container->get('app.leaf');
                Assert::assertInstanceOf(stdClass::class, $leaf);

                return new CaptiveHolder($leaf);
            },
        );

        $scope = $container->createRequestScope();

        try {
            $scope->get('app.captor');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertSame(
                "Service 'app.captor' (singleton) is resolving and would capture the request-scoped service 'app.leaf' "
                . 'beyond the scope it was born in -- the runtime capture the boot graph refuses: '
                . "give 'app.leaf' a singleton or transient lifetime, or make 'app.captor' request-scoped.",
                $requestScopeException->getMessage(),
            );
        }
    }

    public function testTheCaptiveGuardFiresAtTheRootToo(): void
    {
        // Outside any scope the request lane would refuse the
        // outside-scope refusal -- but the capture shape is the deeper
        // defect, so the captor message owns the boot-time resolution
        // as well.
        $container = new Container();
        $container->set('app.leaf', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);
        $container->set(
            'app.captor',
            static function (Container $container): CaptiveHolder {
                $leaf = $container->get('app.leaf');
                Assert::assertInstanceOf(stdClass::class, $leaf);

                return new CaptiveHolder($leaf);
            },
        );

        try {
            $container->get('app.captor');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertSame(
                "Service 'app.captor' (singleton) is resolving and would capture the request-scoped service 'app.leaf' "
                . 'beyond the scope it was born in -- the runtime capture the boot graph refuses: '
                . "give 'app.leaf' a singleton or transient lifetime, or make 'app.captor' request-scoped.",
                $requestScopeException->getMessage(),
            );
        }
    }

    public function testTheCaptorFrameClosesAfterARefusal(): void
    {
        // The frame ledger must not leak a captor: after the refused
        // resolution, a plain request-scoped resolution inside a scope
        // works again, and the failed captor itself never memoises.
        $container = new Container();
        $container->set('app.leaf', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);
        $container->set(
            'app.captor',
            static function (Container $container): CaptiveHolder {
                $leaf = $container->get('app.leaf');
                Assert::assertInstanceOf(stdClass::class, $leaf);

                return new CaptiveHolder($leaf);
            },
        );

        $scope = $container->createRequestScope();

        try {
            $scope->get('app.captor');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException) {
            // the refusal itself
        }

        self::assertInstanceOf(stdClass::class, $scope->get('app.leaf'), 'the ledger kept no stale captor');
        self::assertSame($scope->get('app.leaf'), $scope->get('app.leaf'));
    }

    public function testATransientMayStillResolveARequestScopedId(): void
    {
        // The narrower capture is a fact of construction, not a broken
        // promise -- the same doctrine the boot graph keeps.
        $container = new Container();
        $container->set('app.leaf', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);
        $container->set(
            'app.branch',
            static function (Container $container): CaptiveHolder {
                $leaf = $container->get('app.leaf');
                Assert::assertInstanceOf(stdClass::class, $leaf);

                return new CaptiveHolder($leaf);
            },
            ServiceLifetime::TRANSIENT,
        );

        $scope = $container->createRequestScope();

        $branch = $scope->get('app.branch');
        self::assertInstanceOf(CaptiveHolder::class, $branch);
        self::assertSame($scope->get('app.leaf'), $branch->leaf);
    }

    public function testNestedSingletonsNameTheInnermostCaptor(): void
    {
        $container = new Container();
        $container->set('app.leaf', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);
        $container->set(
            'app.inner',
            static function (Container $container): CaptiveHolder {
                $leaf = $container->get('app.leaf');
                Assert::assertInstanceOf(stdClass::class, $leaf);

                return new CaptiveHolder($leaf);
            },
        );
        $container->set(
            'app.outer',
            static function (Container $container): NestedHolder {
                $inner = $container->get('app.inner');
                Assert::assertInstanceOf(stdClass::class, $inner);

                return new NestedHolder($inner);
            },
        );

        $scope = $container->createRequestScope();

        try {
            $scope->get('app.outer');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertSame(
                "Service 'app.inner' (singleton) is resolving and would capture the request-scoped service 'app.leaf' "
                . 'beyond the scope it was born in -- the runtime capture the boot graph refuses: '
                . "give 'app.leaf' a singleton or transient lifetime, or make 'app.inner' request-scoped.",
                $requestScopeException->getMessage(),
            );
        }
    }
}
