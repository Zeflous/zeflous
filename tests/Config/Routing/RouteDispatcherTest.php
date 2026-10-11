<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use ArrayIterator;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\DispatchOutcome;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteDispatcher;
use Zef\Framework\Config\Routing\RouteHandler;
use Zef\Framework\Config\Routing\RouteHandlerResolver;
use Zef\Framework\Config\Routing\RoutingRuntime;
use Zef\Framework\Container\Container;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Kernel;
use Zef\Test\Support\RecordingController;
use Zef\Test\Support\SealedController;

/**
 * @internal
 */
#[CoversClass(RouteDispatcher::class)]
#[CoversClass(DispatchOutcome::class)]
#[CoversClass(RouteHandlerResolver::class)]
#[CoversClass(RouteHandler::class)]
#[CoversClass(RoutingRuntime::class)]
#[CoversClass(RadixTree::class)]
final class RouteDispatcherTest extends TestCase
{
    public function testExecutesAClosureServiceHandlerWithTheCapturedParameters(): void
    {
        $container = new Container();
        $userHandler = static function (array $parameters): string {
            $id = $parameters['id'] ?? '';
            \assert(\is_string($id));

            return 'user-' . $id;
        };
        $container->set('Users@show', static fn (): callable => $userHandler);

        $dispatcher = $this->dispatcher($this->routes(), $container);

        $dispatchOutcome = $dispatcher->dispatch('GET', '/users/42');

        self::assertTrue($dispatchOutcome->isExecuted());
        self::assertSame('user-42', $dispatchOutcome->result());
    }

    public function testTheHandlerContractIsOneParameterMap(): void
    {
        $container = new Container();
        $container->set('Users@show', static fn (): callable => static fn (array $parameters): array => $parameters);

        $dispatcher = $this->dispatcher($this->routes(), $container);

        $dispatchOutcome = $dispatcher->dispatch('GET', '/users/42');

        self::assertSame(['id' => '42'], $dispatchOutcome->parameters());
        self::assertSame($dispatchOutcome->parameters(), $dispatchOutcome->result());
    }

    public function testTheOutcomeCarriesTheExecutedRouteWithItsMetadata(): void
    {
        $container = new Container();
        $container->set('Users@show', static fn (): callable => static fn (array $parameters): string => 'ok');

        $dispatcher = $this->dispatcher($this->routes(), $container);

        $dispatchOutcome = $dispatcher->dispatch('GET', '/users/42');

        self::assertSame('Users@show', $dispatchOutcome->route()->handler());
        self::assertSame(['auth'], $dispatchOutcome->route()->middleware());
        self::assertSame('scoped', $dispatchOutcome->route()->meta()['scope'] ?? null);
    }

    public function testExecutesAClassMethodHandlerThroughTheContainer(): void
    {
        $recordingController = new RecordingController();
        $container = new Container();
        $container->set(RecordingController::class, static fn (): RecordingController => $recordingController);

        $dispatcher = $this->dispatcher($this->classMethodRoutes(), $container);

        $dispatchOutcome = $dispatcher->dispatch('GET', '/users/42');

        self::assertTrue($dispatchOutcome->isExecuted());
        self::assertSame('shown:42', $dispatchOutcome->result());
        self::assertSame([['id' => '42']], $recordingController->received);
    }

    public function testTheContainerMemoisesTheHandlerInstanceAcrossDispatches(): void
    {
        $recordingController = new RecordingController();
        $container = new Container();
        $container->set(RecordingController::class, static fn (): RecordingController => $recordingController);

        $dispatcher = $this->dispatcher($this->classMethodRoutes(), $container);

        $dispatcher->dispatch('GET', '/users/1');
        $dispatcher->dispatch('GET', '/users/2');

        self::assertSame(2, $recordingController->calls);
        self::assertSame([['id' => '1'], ['id' => '2']], $recordingController->received);
    }

    public function testMethodDisjointRoutesDispatchToTheirOwnHandlers(): void
    {
        $container = new Container();
        $container->set('photos.index', static fn (): callable => static fn (array $parameters): string => 'index');
        $container->set('photos.store', static fn (): callable => static fn (array $parameters): string => 'store');

        $dispatcher = $this->dispatcher([
            ['path' => '/photos', 'method' => ['GET'], 'handler' => 'photos.index'],
            ['path' => '/photos', 'method' => ['POST'], 'handler' => 'photos.store'],
        ], $container);

        self::assertSame('index', $dispatcher->dispatch('GET', '/photos')->result());
        self::assertSame('store', $dispatcher->dispatch('POST', '/photos')->result());
    }

    public function testDispatchAcceptsARawMethod(): void
    {
        $container = new Container();
        $container->set('Users@show', static fn (): callable => static fn (array $parameters): string => 'ok');

        $dispatcher = $this->dispatcher($this->routes(), $container);

        self::assertTrue($dispatcher->dispatch('get', '/users/42')->isExecuted());
    }

    public function testNoRouteOwnsThePath(): void
    {
        $dispatcher = $this->dispatcher($this->routes(), new Container());

        $dispatchOutcome = $dispatcher->dispatch('GET', '/missing');

        self::assertFalse($dispatchOutcome->isExecuted());
        self::assertFalse($dispatchOutcome->isMethodNotAllowed());
    }

    public function testTheMethodNotAllowedOutcomeCarriesTheSortedAllowedMethods(): void
    {
        $dispatcher = $this->dispatcher($this->routes(), new Container());

        $dispatchOutcome = $dispatcher->dispatch('DELETE', '/users/42');

        self::assertTrue($dispatchOutcome->isMethodNotAllowed());
        self::assertSame(['GET', 'POST'], $dispatchOutcome->allowedMethods());
    }

    public function testTheNotFoundOutcomeRefusesItsAccessors(): void
    {
        $dispatcher = $this->dispatcher($this->routes(), new Container());

        $dispatchOutcome = $dispatcher->dispatch('GET', '/missing');

        $this->assertOutcomeRefuses(
            $dispatchOutcome,
            static fn (DispatchOutcome $dispatchOutcome): CompiledRoute => $dispatchOutcome->route(),
            'The dispatch outcome does not hold a route.',
        );
    }

    public function testTheMethodNotAllowedOutcomeRefusesTheExecutedAccessors(): void
    {
        $dispatcher = $this->dispatcher($this->routes(), new Container());

        $dispatchOutcome = $dispatcher->dispatch('DELETE', '/users/42');

        $this->assertOutcomeRefuses(
            $dispatchOutcome,
            static fn (DispatchOutcome $dispatchOutcome): array => $dispatchOutcome->parameters(),
            'The dispatch outcome does not hold parameters.',
        );
    }

    public function testTheExecutedOutcomeRefusesTheAllowedMethodsAccessor(): void
    {
        $container = new Container();
        $container->set('Users@show', static fn (): callable => static fn (array $parameters): string => 'ok');

        $dispatcher = $this->dispatcher($this->routes(), $container);

        $dispatchOutcome = $dispatcher->dispatch('GET', '/users/42');

        $this->assertOutcomeRefuses(
            $dispatchOutcome,
            static fn (DispatchOutcome $dispatchOutcome): array => $dispatchOutcome->allowedMethods(),
            'The dispatch outcome does not hold allowed methods.',
        );
    }

    public function testAnUnknownHandlerServiceFailsClosed(): void
    {
        $dispatcher = $this->dispatcher([
            ['path' => '/plain', 'method' => ['GET'], 'handler' => 'unregistered'],
        ], new Container());

        $this->assertDispatchingRejects(
            $dispatcher,
            'GET',
            '/plain',
            'The route handler "unregistered" is not a registered service.',
        );
    }

    public function testANonCallableServiceFailsClosed(): void
    {
        $container = new Container();
        $container->set('Users@show', static fn (): int => 42);

        $dispatcher = $this->dispatcher($this->routes(), $container);

        $this->assertDispatchingRejects(
            $dispatcher,
            'GET',
            '/users/42',
            'The route handler "Users@show" does not resolve to a callable.',
        );
    }

    public function testAClassMethodHandlerWithAnUnregisteredClassFailsClosed(): void
    {
        $dispatcher = $this->dispatcher($this->classMethodRoutes(), new Container());

        $this->assertDispatchingRejects(
            $dispatcher,
            'GET',
            '/users/42',
            'The route handler "Zef\Test\Support\RecordingController@show" names the service '
            . '"Zef\Test\Support\RecordingController", which is not registered.',
        );
    }

    public function testAClassMethodHandlerWithAMissingMethodFailsClosed(): void
    {
        $container = new Container();
        $container->set(ArrayIterator::class, static fn (): ArrayIterator => new ArrayIterator([]));

        $dispatcher = $this->dispatcher([
            ['path' => '/items', 'method' => ['GET'], 'handler' => ArrayIterator::class . '@countThem'],
        ], $container);

        $this->assertDispatchingRejects(
            $dispatcher,
            'GET',
            '/items',
            'The route handler "ArrayIterator@countThem" does not resolve to a callable.',
        );
    }

    public function testAPrivateMethodIsNotInvokableAndFailsClosed(): void
    {
        $container = new Container();
        $container->set(SealedController::class, static fn (): SealedController => new SealedController());

        $dispatcher = $this->dispatcher([
            ['path' => '/sealed', 'method' => ['GET'], 'handler' => SealedController::class . '@reveal'],
        ], $container);

        $this->assertDispatchingRejects(
            $dispatcher,
            'GET',
            '/sealed',
            'The route handler "Zef\Test\Support\SealedController@reveal" does not resolve to a callable.',
        );
    }

    public function testATwoSeparatorHandlerStaysOnePairAndFailsClosed(): void
    {
        $container = new Container();
        $container->set(ArrayIterator::class, static fn (): ArrayIterator => new ArrayIterator([]));
        $dispatcher = $this->dispatcher([
            ['path' => '/pairs', 'method' => ['GET'], 'handler' => ArrayIterator::class . '@count@x'],
        ], $container);

        $this->assertDispatchingRejects(
            $dispatcher,
            'GET',
            '/pairs',
            'The route handler "ArrayIterator@count@x" does not resolve to a callable.',
        );
    }

    public function testASeparatorOnlyHandlerFailsClosed(): void
    {
        $dispatcher = $this->dispatcher([
            ['path' => '/edge', 'method' => ['GET'], 'handler' => '@show'],
        ], new Container());

        $this->assertDispatchingRejects(
            $dispatcher,
            'GET',
            '/edge',
            'The route handler "@show" names the service "", which is not registered.',
        );
    }

    public function testTheKernelEndToEndPipelineExecutesAHandler(): void
    {
        $container = new Container();
        $container->set('Health@check', static fn (): callable => static fn (array $parameters): string => 'healthy');

        $kernel = new Kernel($container, new Config(['routes' => $this->routes()]));
        $routeDispatcher = new RouteDispatcher(RoutingRuntime::fromConfig($kernel->config()), $kernel->container());

        $dispatchOutcome = $routeDispatcher->dispatch('GET', '/health');

        self::assertTrue($dispatchOutcome->isExecuted());
        self::assertSame('healthy', $dispatchOutcome->result());
        self::assertSame([], $dispatchOutcome->parameters());
    }

    /**
     * @param list<array<string, mixed>> $routes
     */
    private function dispatcher(array $routes, Container $container): RouteDispatcher
    {
        return new RouteDispatcher(RoutingRuntime::fromConfig(new Config(['routes' => $routes])), $container);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routes(): array
    {
        return [
            ['path' => '/health', 'method' => ['GET'], 'handler' => 'Health@check', 'name' => 'health'],
            [
                'path' => '/users/{id}',
                'method' => ['GET', 'POST'],
                'handler' => 'Users@show',
                'constraints' => ['id' => '\d+'],
                'middleware' => ['auth'],
                'meta' => ['scope' => 'scoped'],
                'name' => 'users.show',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function classMethodRoutes(): array
    {
        return [
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => RecordingController::class . '@show',
                'constraints' => ['id' => '\d+'],
            ],
        ];
    }

    private function assertDispatchingRejects(
        RouteDispatcher $routeDispatcher,
        string $method,
        string $path,
        string $message,
    ): void {
        try {
            $routeDispatcher->dispatch($method, $path);
            self::fail('Dispatching must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame($message, $configException->getMessage());
        }
    }

    /**
     * @param Closure(DispatchOutcome): mixed $accessor
     */
    private function assertOutcomeRefuses(DispatchOutcome $dispatchOutcome, Closure $accessor, string $message): void
    {
        try {
            $accessor($dispatchOutcome);
            self::fail('The accessor must refuse this outcome.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame($message, $invariantViolationException->getMessage());
        }
    }
}
