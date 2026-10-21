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
use Zef\Framework\Config\Routing\DispatchOutcome;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteDispatcher;
use Zef\Framework\Container\Container;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Http\Psr17Factory;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\ConditionalRequests;
use Zef\Framework\Middleware\EntityTagMatch;
use Zef\Framework\Middleware\LastModifiedMatch;
use Zef\Framework\Middleware\MiddlewareException;
use Zef\Framework\Middleware\MiddlewarePipeline;
use Zef\Framework\Middleware\NotModifiedResponse;
use Zef\Framework\Middleware\RequestAuthority;
use Zef\Framework\Middleware\RouteDispatchHandler;
use Zef\Framework\Middleware\RouterRequestHandler;
use Zef\Framework\Middleware\RouterRequestMatcher;
use Zef\Framework\Middleware\ServerRequestMetadata;
use Zef\Framework\Router\Router;

/**
 * @internal
 */
#[CoversClass(ConditionalRequests::class)]
#[CoversClass(DispatchOutcome::class)]
#[CoversClass(EntityTagMatch::class)]
#[CoversClass(LastModifiedMatch::class)]
#[CoversClass(MatchResult::class)]
#[CoversClass(NotModifiedResponse::class)]
#[CoversClass(RequestAuthority::class)]
#[CoversClass(RouteDispatcher::class)]
#[CoversClass(RouterRequestHandler::class)]
#[CoversClass(RouterRequestMatcher::class)]
#[CoversClass(ServerRequestMetadata::class)]
#[CoversClass(RouteDispatchHandler::class)]
#[CoversClass(MiddlewarePipeline::class)]
#[CoversClass(MiddlewareException::class)]
final class RouterRequestHandlerTest extends TestCase
{
    public function testDispatchesPsr7RequestAndAddsVaryAccept(): void
    {
        $container = $this->containerWithProfileHandler();
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'host' => 'api.example.com',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'produces' => ['application/json'],
            ],
        ]);
        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com:8080', 'application/json'))
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('profile', $response->getBody()->getContents());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('Accept', $response->getHeaderLine('Vary'));
    }

    public function testMapsNotFoundMethodNotAllowedAndNotAcceptableToHttpResponses(): void
    {
        $container = $this->containerWithProfileHandler();
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'produces' => ['application/json'],
            ],
        ]);
        $handler = $this->handler($router, $container);

        $missing = $handler->handle($this->request('GET', 'api.example.com', 'application/json', '/missing'));
        self::assertSame(404, $missing->getStatusCode());
        self::assertSame('application/problem+json', $missing->getHeaderLine('Content-Type'));
        $document = json_decode($missing->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame('Not Found', $document['title']);

        $methodMismatch = $handler->handle($this->request('POST', 'api.example.com', 'application/json'));
        self::assertSame(405, $methodMismatch->getStatusCode());
        self::assertSame('GET', $methodMismatch->getHeaderLine('Allow'));
        self::assertSame('application/problem+json', $methodMismatch->getHeaderLine('Content-Type'));

        $notAcceptable = $handler->handle($this->request('GET', 'api.example.com', 'text/html'));
        self::assertSame(406, $notAcceptable->getStatusCode());
        self::assertSame('Accept', $notAcceptable->getHeaderLine('Vary'));
        self::assertSame('application/problem+json', $notAcceptable->getHeaderLine('Content-Type'));
        self::assertStringContainsString('application/json', $notAcceptable->getBody()->getContents());
    }

    public function testIfNoneMatchReturnsNotModifiedWithoutExecutingASecondRepresentationBody(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json', 'ETag' => 'W/"profile-v1"', 'X-Resource' => 'profile'],
                '{"name":"profile"}',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $request = $this->request(
            'GET',
            'api.example.com',
            'application/json',
            extraHeaders: ['If-None-Match' => '"other", "profile-v1"'],
        );
        $response = $this->handler($router, $container)->handle($request);

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('W/"profile-v1"', $response->getHeaderLine('ETag'));
        self::assertSame('profile', $response->getHeaderLine('X-Resource'));
        self::assertSame('', $response->getBody()->getContents());
    }

    public function testIfModifiedSinceReturnsNotModifiedWhenRepresentationIsNotNewer(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                [
                    'Content-Type' => 'text/plain',
                    'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
                ],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $request = $this->request(
            'GET',
            'api.example.com',
            'text/plain',
            extraHeaders: ['If-Modified-Since' => 'Wed, 21 Oct 2015 07:28:00 GMT'],
        );
        $response = $this->handler($router, $container)->handle($request);

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('Wed, 21 Oct 2015 07:28:00 GMT', $response->getHeaderLine('Last-Modified'));
        self::assertSame('', $response->getBody()->getContents());
    }

    public function testIfNoneMatchTakesPrecedenceOverIfModifiedSince(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                [
                    'Content-Type' => 'text/plain',
                    'ETag' => '"profile-v2"',
                    'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
                ],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $request = $this->request(
            'GET',
            'api.example.com',
            'text/plain',
            extraHeaders: [
                'If-None-Match' => '"different"',
                'If-Modified-Since' => 'Wed, 21 Oct 2030 07:28:00 GMT',
            ],
        );
        $response = $this->handler($router, $container)->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('profile', $response->getBody()->getContents());
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

        $response = $this->handler($router, $container)
            ->handle($this->request('HEAD', 'api.example.com', 'text/plain'))
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('profile', $response->getHeaderLine('X-Resource'));
        self::assertSame('', $response->getBody()->getContents());
        self::assertSame(1, $calls);
    }

    public function testExplicitHeadRouteTakesPrecedenceOverGetFallback(): void
    {
        $container = new Container();
        $container->set(
            'Head@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                204,
                ['X-Route' => 'head'],
                '',
            ),
        );
        $container->set(
            'Get@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['X-Route' => 'get'],
                'get body',
            ),
        );

        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Get@show'],
            ['path' => '/profile', 'method' => ['HEAD'], 'handler' => 'Head@show'],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('HEAD', 'api.example.com', 'text/plain'))
        ;

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('head', $response->getHeaderLine('X-Route'));
        self::assertSame('', $response->getBody()->getContents());
    }

    public function testUsesAcceptVersionHeaderToSelectRouteVariant(): void
    {
        $container = new Container();
        $container->set(
            'ProfileV1@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'text/plain'],
                'v1',
            ),
        );
        $container->set(
            'ProfileV2@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'text/plain'],
                'v2',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'ProfileV1@show', 'version' => 'v1'],
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'ProfileV2@show', 'version' => 'v2'],
        ]);

        $response = $this->handler($router, $container)->handle(
            $this->request('GET', 'api.example.com', 'text/plain', '/profile', 'v2'),
        );

        self::assertSame('v2', $response->getBody()->getContents());
    }

    public function testExecutesRouteMiddlewareAroundTheTerminalHandler(): void
    {
        $container = $this->containerWithProfileHandler();
        $container->set('route.audit', static fn (): MiddlewareInterface => new class implements MiddlewareInterface {
            #[Override]
            public function process(
                ServerRequestInterface $serverRequest,
                RequestHandlerInterface $requestHandler,
            ): ResponseInterface {
                $response = $requestHandler->handle($serverRequest)->withHeader('X-Route-Middleware', 'ran');
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

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

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
        $this->expectExceptionMessageIsOrContains('Route middleware service "missing.middleware" is not registered.');

        $this->handler($router, new Container())->handle($this->request('GET', 'api.example.com', 'application/json'));
    }

    public function testRefusesAHandlerThatDoesNotReturnAResponse(): void
    {
        $container = new Container();
        $container->set(
            'Chatty@show',
            static fn (): callable => static fn (array $parameters): string => 'not a response',
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Chatty@show'],
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains(
            'The HTTP route handler "Chatty@show" must return a ResponseInterface.',
        );

        $this->handler($router, $container)->handle($this->request('GET', 'api.example.com', 'application/json'));
    }

    public function testAWildcardVaryHeaderIsPreservedVerbatim(): void
    {
        $container = new Container();
        $container->set(
            'Vary@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json', 'Vary' => '*'],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Vary@show', 'produces' => ['application/json']],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame('*', $response->getHeaderLine('Vary'));
    }

    public function testAnExistingAcceptVaryTokenIsNotDuplicated(): void
    {
        $container = new Container();
        $container->set(
            'Vary@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json', 'Vary' => 'Accept-Encoding, Accept'],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Vary@show', 'produces' => ['application/json']],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame('Accept-Encoding, Accept', $response->getHeaderLine('Vary'));
    }

    public function testANotFoundOutcomeRefusesToYieldAResult(): void
    {
        $outcome = DispatchOutcome::notFound();

        self::assertFalse($outcome->isExecuted());
        $this->expectException(InvariantViolationException::class);

        $outcome->result();
    }

    public function testTheHostHeaderWinsOverTheUriHost(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'host' => 'api.example.com', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $serverRequest = new ServerRequest(
            'GET',
            new Uri('https://other.example.com/profile'),
            headers: ['Host' => 'api.example.com', 'Accept' => 'application/json'],
        );
        $response = $this->handler($router, $container)->handle($serverRequest);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testAPortOfZeroRefusesTheHost(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'host' => 'api.example.com', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com:0', 'application/json'))
        ;

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAPortOfOneKeepsTheHost(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'host' => 'api.example.com', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com:1', 'application/json'))
        ;

        self::assertSame(200, $response->getStatusCode());
    }

    public function testTheMaximumPortKeepsTheHost(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'host' => 'api.example.com', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com:65535', 'application/json'))
        ;

        self::assertSame(200, $response->getStatusCode());
    }

    public function testAnOutOfRangePortRefusesTheHost(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'host' => 'api.example.com', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com:99999', 'application/json'))
        ;

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAConditionalMatchOnAPostRequestIsIgnored(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json', 'ETag' => 'W/"profile-v1"'],
                '{"name":"profile"}',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['POST'], 'handler' => 'Profile@show'],
        ]);

        $request = $this->request(
            'POST',
            'api.example.com',
            'application/json',
            extraHeaders: ['If-None-Match' => '"profile-v1"'],
        );
        $response = $this->handler($router, $container)->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"name":"profile"}', $response->getBody()->getContents());
    }

    public function testAnUnparsableLastModifiedNeverYieldsNotModified(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json', 'Last-Modified' => 'not-a-date'],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $request = $this->request(
            'GET',
            'api.example.com',
            'application/json',
            extraHeaders: ['If-Modified-Since' => 'Wed, 21 Oct 2099 07:28:00 GMT'],
        );
        $response = $this->handler($router, $container)->handle($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testAnUnparsableConditionalDateNeverYieldsNotModified(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json', 'Last-Modified' => 'Thu, 01 Jan 1970 00:00:00 GMT'],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $request = $this->request(
            'GET',
            'api.example.com',
            'application/json',
            extraHeaders: ['If-Modified-Since' => 'not-a-date'],
        );
        $response = $this->handler($router, $container)->handle($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testANotAcceptableResponseCarriesTheSupportedMediaTypesDetail(): void
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

        $response = $this->handler($router, $container)->handle($this->request('GET', 'api.example.com', 'text/html'));

        self::assertSame(406, $response->getStatusCode());
        $document = json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame('about:blank', $document['type']);
        self::assertSame('Not Acceptable', $document['title']);
        self::assertSame('Supported media types: application/json', $document['detail']);
    }

    public function testAProblemResponseCarriesTheBlankProblemType(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'host' => 'api.example.com', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json', '/missing'))
        ;

        $document = json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame('about:blank', $document['type']);
        self::assertSame('Not Found', $document['title']);
    }

    public function testARedirectStatusKeepsItsBody(): void
    {
        $container = new Container();
        $container->set(
            'Moved@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                303,
                ['Location' => '/elsewhere'],
                'redirecting',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Moved@show'],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('redirecting', $response->getBody()->getContents());
    }

    public function testAHandlerProvidedNotModifiedResponseIsBodyless(): void
    {
        $container = new Container();
        $container->set(
            'Cached@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                304,
                ['ETag' => '"v1"'],
                'leftover',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Cached@show'],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('', $response->getBody()->getContents());
    }

    public function testAUriPathWithoutTheLeadingSlashStillMatches(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $uri = new Uri('https://api.example.com/')->withPath('profile');
        $serverRequest = new ServerRequest(
            'GET',
            $uri,
            headers: ['Host' => 'api.example.com', 'Accept' => 'application/json'],
        );
        $response = $this->handler($router, $container)->handle($serverRequest);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testAnExactAcceptVaryTokenAloneIsNotDuplicated(): void
    {
        $container = new Container();
        $container->set(
            'Vary@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json', 'Vary' => 'Accept'],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Vary@show', 'produces' => ['application/json']],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame('Accept', $response->getHeaderLine('Vary'));
    }

    private function containerWithProfileHandler(): Container
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

    private function handler(Router $router, Container $container): RouterRequestHandler
    {
        $psr17Factory = new Psr17Factory();

        return new RouterRequestHandler($router, $container, $psr17Factory, $psr17Factory);
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    private function request(
        string $method,
        string $host,
        string $accept,
        string $path = '/profile',
        ?string $version = null,
        array $extraHeaders = [],
    ): ServerRequest {
        $headers = ['Host' => $host, 'Accept' => $accept];
        if ($version !== null) {
            $headers['Accept-Version'] = $version;
        }

        return new ServerRequest(
            $method,
            new Uri('https://' . explode(':', $host, 2)[0] . $path),
            headers: $headers + $extraHeaders,
        );
    }
}
