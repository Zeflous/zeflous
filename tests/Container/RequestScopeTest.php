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
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceStore;
use Zef\Test\Support\Container\ScopedBranch;

/**
 * @internal
 */
#[CoversClass(ActiveScope::class)]
#[CoversClass(RequestInstances::class)]
#[CoversClass(RequestScope::class)]
#[CoversClass(RequestScopeRefusals::class)]
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
}
