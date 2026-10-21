<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;
use Zef\Framework\Container\Container;
use Zef\Framework\Http\Psr17Factory;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\MiddlewareException;
use Zef\Framework\Middleware\MiddlewarePipeline;
use Zef\Framework\Middleware\RouteDispatchHandler;
use Zef\Framework\Middleware\RouterRequestHandler;
use Zef\Framework\Router\Router;

#[CoversClass(RouterRequestHandler::class)]
#[CoversClass(RouteDispatchHandler::class)]
#[CoversClass(MiddlewarePipeline::class)]
#[CoversClass(MiddlewareException::class)]
final class RouterRequestHandlerTest extends TestCase
{
    public function testDispatchesPsr7RequestAndAddsVaryAccept(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'host' => 'api.example.com',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'produces' => ['application/json'],
            ],
        ]);
        $response = self::handler($router, $container)->handle(self::request('GET', 'api.example.com:8080', 'application/json'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('profile', $response->getBody()->getContents());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('Accept', $response->getHeaderLine('Vary'));
    }

    public function testMapsNotFoundMethodNotAllowedAndNotAcceptableToHttpResponses(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'produces' => ['application/json'],
            ],
        ]);
        $handler = self::handler($router, $container);

        $missing = $handler->handle(self::request('GET', 'api.example.com', 'application/json', '/missing'));
        self::assertSame(404, $missing->getStatusCode());

        $methodMismatch = $handler->handle(self::request('POST', 'api.example.com', 'application/json'));
        self::assertSame(405, $methodMismatch->getStatusCode());
        self::assertSame('GET', $methodMismatch->getHeaderLine('Allow'));

        $notAcceptable = $handler->handle(self::request('GET', 'api.example.com', 'text/html'));
        self::assertSame(406, $notAcceptable->getStatusCode());
        self::assertSame('Accept', $notAcceptable->getHeaderLine('Vary'));
        self::assertStringContainsString('application/json', $notAcceptable->getBody()->getContents());
    }

    public function testHeadFallsBackToGetAndSuppressesResponseBody(): void
    {
        $container = new Container();
        $calls = 0;
        $container->set(
            'Profile@show',
            static function () use (&$calls): callable {
                return static function (array $parameters) use (&$calls): ResponseInterface {
                    ++$calls;

                    return new Response(200, ['Content-Type' => 'text/plain', 'X-Resource' => 'profile'], 'body');
                };
            },
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $response = self::handler($router, $container)->handle(self::request('HEAD', 'api.example.com', 'text/plain'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('profile', $response->getHeaderLine('X-Resource'));
        self::assertSame('', $response->getBody()->getContents());
        self::assertSame(1, $calls);
    }

    public function testExplicitHeadRouteTakesPrecedenceOverGetFallback(): void
    {
        $container = new Container();
        $container->set('Head@show', static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(204, ['X-Route' => 'head'], ''));
        $container->set('Get@show', static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(200, ['X-Route' => 'get'], 'get body'));
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Get@show'],
            ['path' => '/profile', 'method' => ['HEAD'], 'handler' => 'Head@show'],
        ]);

        $response = self::handler($router, $container)->handle(self::request('HEAD', 'api.example.com', 'text/plain'));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('head', $response->getHeaderLine('X-Route'));
        self::assertSame('', $response->getBody()->getContents());
    }

    public function testUsesAcceptVersionHeaderToSelectRouteVariant(): void
    {
        $container = new Container();
        $container->set(
            'ProfileV1@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(200, ['Content-Type' => 'text/plain'], 'v1'),
        );
        $container->set(
            'ProfileV2@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(200, ['Content-Type' => 'text/plain'], 'v2'),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'ProfileV1@show', 'version' => 'v1'],
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'ProfileV2@show', 'version' => 'v2'],
        ]);

        $response = self::handler($router, $container)->handle(
            self::request('GET', 'api.example.com', 'text/plain', '/profile', 'v2'),
        );

        self::assertSame('v2', $response->getBody()->getContents());
    }

    public function testExecutesRouteMiddlewareAroundTheTerminalHandler(): void
    {
        $container = self::containerWithProfileHandler();
        $container->set('route.audit', static fn (): MiddlewareInterface => new class implements MiddlewareInterface {
            #[Override]
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request)->withHeader('X-Route-Middleware', 'ran');
                if (!$response instanceof ResponseInterface) {
                    throw new UnexpectedValueException('Middleware must return a response.');
                }

                return $response;
            }
        });
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'middleware' => ['route.audit'],
            ],
        ]);

        $response = self::handler($router, $container)->handle(self::request('GET', 'api.example.com', 'application/json'));

        self::assertSame('ran', $response->getHeaderLine('X-Route-Middleware'));
    }

    public function testMissingRouteMiddlewareFailsClosed(): void
    {
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'middleware' => ['missing.middleware'],
            ],
        ]);

        $this->expectException(MiddlewareException::class);
        $this->expectExceptionMessage('Route middleware service "missing.middleware" is not registered.');

        self::handler($router, new Container())->handle(self::request('GET', 'api.example.com', 'application/json'));
    }

    private static function containerWithProfileHandler(): Container
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json'],
                'profile',
            ),
        );

        return $container;
    }

    private static function handler(Router $router, Container $container): RouterRequestHandler
    {
        $factory = new Psr17Factory();

        return new RouterRequestHandler($router, $container, $factory, $factory);
    }

    private static function request(
        string $method,
        string $host,
        string $accept,
        string $path = '/profile',
        ?string $version = null,
    ): ServerRequest {
        $headers = ['Host' => $host, 'Accept' => $accept];
        if ($version !== null) {
            $headers['Accept-Version'] = $version;
        }

        return new ServerRequest(
            $method,
            new Uri('https://' . explode(':', $host, 2)[0] . $path),
            headers: $headers,
        );
    }
}
