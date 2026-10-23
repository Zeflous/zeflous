<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use UnexpectedValueException;
use Zef\Framework\Config\Routing\DispatchOutcome;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteDispatcher;
use Zef\Framework\Config\Routing\RouteMatchExplanation;
use Zef\Framework\Container\Container;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Exception\MethodNotAllowedException;
use Zef\Framework\Exception\NotAcceptableException;
use Zef\Framework\Exception\NotFoundException;
use Zef\Framework\Http\HttpDate;
use Zef\Framework\Http\Psr17Factory;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\ConditionalRequests;
use Zef\Framework\Middleware\CorrelationMiddleware;
use Zef\Framework\Middleware\DeprecationLinkHeader;
use Zef\Framework\Middleware\EntityTagMatch;
use Zef\Framework\Middleware\ErrorMappingMiddleware;
use Zef\Framework\Middleware\LastModifiedMatch;
use Zef\Framework\Middleware\MiddlewareException;
use Zef\Framework\Middleware\MiddlewarePipeline;
use Zef\Framework\Middleware\NotModifiedResponse;
use Zef\Framework\Middleware\RequestAuthority;
use Zef\Framework\Middleware\RouteDeprecationHeaders;
use Zef\Framework\Middleware\RouteDispatchHandler;
use Zef\Framework\Middleware\RouteMatchTelemetry;
use Zef\Framework\Middleware\RouterRequestHandler;
use Zef\Framework\Middleware\RouterRequestMatcher;
use Zef\Framework\Middleware\ServerRequestMetadata;
use Zef\Framework\Router\Router;
use Zef\Framework\Telemetry\BatchSpanProcessor;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\InMemorySpanExporter;
use Zef\Framework\Telemetry\MeterSlot;
use Zef\Framework\Telemetry\SpanData;
use Zef\Framework\Telemetry\TelemetryClock;
use Zef\Framework\Telemetry\Tracer;
use Zef\Test\Support\Log\RecordingLogger;
use Zef\Test\Support\Telemetry\SteppingTelemetryClock;

/**
 * @internal
 */
#[CoversClass(ConditionalRequests::class)]
#[CoversClass(DeprecationLinkHeader::class)]
#[CoversClass(DispatchOutcome::class)]
#[CoversClass(EntityTagMatch::class)]
#[CoversClass(LastModifiedMatch::class)]
#[CoversClass(HttpDate::class)]
#[CoversClass(MatchResult::class)]
#[CoversClass(NotModifiedResponse::class)]
#[CoversClass(RequestAuthority::class)]
#[CoversClass(RouteDeprecationHeaders::class)]
#[CoversClass(RouteDispatcher::class)]
#[CoversClass(RouterRequestHandler::class)]
#[CoversClass(RouterRequestMatcher::class)]
#[CoversClass(ServerRequestMetadata::class)]
#[CoversClass(RouteDispatchHandler::class)]
#[CoversClass(MiddlewarePipeline::class)]
#[CoversClass(MiddlewareException::class)]
final class RouterRequestHandlerTest extends TestCase
{
    private ?string $routedErrorLog = null;

    private false|string $previousErrorLog = false;

    #[Override]
    protected function setUp(): void
    {
        // The boundary-wrapped tests drive the error recorder's last-resort
        // SAPI writes (the no-logger battery records every refusal). Routing
        // that destination once up front keeps those writes observable and
        // off the process's real stderr: Infection's initial test run
        // terminates the suite on the first stderr byte it sees, so an
        // unrouted write kills the mutation lane before a single mutant is
        // generated.
        $this->routedErrorLog = sys_get_temp_dir() . '/zef-router-errorlog-' . uniqid() . '.log';
        $this->previousErrorLog = ini_set('error_log', $this->routedErrorLog);
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->routedErrorLog === null) {
            return;
        }

        ini_set('error_log', (string) $this->previousErrorLog);
        if (is_file($this->routedErrorLog) && !unlink($this->routedErrorLog)) {
            throw new RuntimeException('Unable to remove the temporary error log.');
        }
    }

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

    public function testTheDispatchTranslationRaisesTheTypedRefusalFamily(): void
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

        try {
            $handler->handle($this->request('GET', 'api.example.com', 'application/json', '/missing'));
            self::fail('An unmatched path must raise the typed not-found refusal.');
        } catch (NotFoundException $notFoundException) {
            self::assertSame('No route matches the requested resource.', $notFoundException->getMessage());
            self::assertSame('N_F', $notFoundException->getErrorCode());
        }

        try {
            $handler->handle($this->request('POST', 'api.example.com', 'application/json'));
            self::fail('A method mismatch must raise the typed method-not-allowed refusal.');
        } catch (MethodNotAllowedException $methodNotAllowedException) {
            self::assertSame('POST', $methodNotAllowedException->method);
            self::assertSame('/profile', $methodNotAllowedException->path);
            self::assertSame(['GET'], $methodNotAllowedException->allowedMethods);
            self::assertSame("Method 'POST' is not allowed for /profile.", $methodNotAllowedException->getMessage());
        }

        try {
            $handler->handle($this->request('GET', 'api.example.com', 'text/html'));
            self::fail('A rejected negotiation must raise the typed not-acceptable refusal.');
        } catch (NotAcceptableException $notAcceptableException) {
            self::assertSame(['application/json'], $notAcceptableException->acceptableMediaTypes);
            self::assertSame('Supported media types: application/json', $notAcceptableException->getMessage());
        }
    }

    public function testAHeadRequestMissRaisesTheSameTypedRefusal(): void
    {
        $container = $this->containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        try {
            $this->handler($router, $container)
                ->handle($this->request('HEAD', 'api.example.com', 'application/json', '/missing'))
            ;
            self::fail('A HEAD miss must raise the same typed not-found refusal.');
        } catch (NotFoundException $notFoundException) {
            self::assertSame('No route matches the requested resource.', $notFoundException->getMessage());
        }
    }

    public function testTheErrorBoundaryAnswersTheRouterRefusalsWithTheFrameworkProblemPosture(): void
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

        $response = $this->boundary()->process(
            $this->request('GET', 'api.example.com', 'application/json', '/missing', extraHeaders: [
                'X-Request-ID' => 'req-router-404',
            ]),
            $handler,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('Accept', $response->getHeaderLine('Vary'));
        self::assertSame('req-router-404', $response->getHeaderLine('X-Request-ID'));

        $document = json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame('https://zef.io/errors/not-found', $document['type']);
        self::assertSame('Resource Not Found', $document['title']);
        self::assertSame(404, $document['status']);
        self::assertSame('No route matches the requested resource.', $document['detail']);
        self::assertSame('/missing', $document['instance']);
        self::assertSame('N_F', $document['code']);
        self::assertSame('req-router-404', $document['trace_id']);
    }

    public function testTheErrorBoundaryAnswersAMethodRefusalWithAllowFromThePayload(): void
    {
        $container = $this->containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $response = $this->boundary()->process(
            $this->request('POST', 'api.example.com', 'application/json'),
            $this->handler($router, $container),
        );

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET', $response->getHeaderLine('Allow'));

        $document = json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame('https://zef.io/errors/method-not-allowed', $document['type']);
        self::assertSame("Method 'POST' is not allowed for /profile.", $document['detail']);
        self::assertSame('M_N_A', $document['code']);
    }

    public function testTheErrorBoundaryAnswersANegotiationRefusalWithTheSupportedTypes(): void
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

        $response = $this->boundary()->process(
            $this->request('GET', 'api.example.com', 'application/xml'),
            $this->handler($router, $container),
        );

        self::assertSame(406, $response->getStatusCode());
        self::assertSame('Accept', $response->getHeaderLine('Vary'));

        $document = json_decode($response->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame('https://zef.io/errors/not-acceptable', $document['type']);
        self::assertSame('Not Acceptable', $document['title']);
        self::assertSame('Supported media types: application/json', $document['detail']);
        self::assertSame('N_A', $document['code']);
    }

    public function testTheErrorBoundaryNegotiationPreferringHtmlAnswersTheHtmlFallback(): void
    {
        $container = $this->containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $response = $this->boundary()->process(
            $this->request('GET', 'api.example.com', 'text/html', '/missing'),
            $this->handler($router, $container),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Resource Not Found', $response->getBody()->getContents());
    }

    public function testARouterRefusalIsLoggedAtTheClientErrorWarningLevel(): void
    {
        $recordingLogger = new RecordingLogger();
        $container = $this->containerWithProfileHandler();
        $router = Router::fromRoutes([
            ['path' => '/profile', 'method' => ['GET'], 'handler' => 'Profile@show'],
        ]);

        $this->boundary($recordingLogger)->process(
            $this->request('GET', 'api.example.com', 'application/json', '/missing'),
            $this->handler($router, $container),
        );

        self::assertCount(1, $recordingLogger->calls);
        self::assertSame('warning', $recordingLogger->calls[0][0]);
        self::assertSame(NotFoundException::class, $recordingLogger->calls[0][2]['exception_class']);
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

        try {
            $this->handler($router, $container)
                ->handle($this->request('GET', 'api.example.com:0', 'application/json'))
            ;
            self::fail('A refused host must raise the typed not-found refusal.');
        } catch (NotFoundException $notFoundException) {
            self::assertSame('No route matches the requested resource.', $notFoundException->getMessage());
        }
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

        try {
            $this->handler($router, $container)
                ->handle($this->request('GET', 'api.example.com:99999', 'application/json'))
            ;
            self::fail('An out-of-range port must raise the typed not-found refusal.');
        } catch (NotFoundException $notFoundException) {
            self::assertSame('No route matches the requested resource.', $notFoundException->getMessage());
        }
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

    public function testADeprecatedRouteAnswersTheRfc9745Posture(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'deprecation' => [
                    'date' => '2023-06-30T23:59:59Z',
                    'sunset' => '2024-06-30T00:00:00Z',
                    'link' => 'https://example.com/deprecations/profile',
                ],
            ],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('@1688169599', $response->getHeaderLine('Deprecation'));
        self::assertSame('Sun, 30 Jun 2024 00:00:00 GMT', $response->getHeaderLine('Sunset'));
        self::assertSame(
            '<https://example.com/deprecations/profile>; rel="deprecation"',
            $response->getHeaderLine('Link'),
        );
    }

    public function testADateOnlyDeprecationAnswersNoSunsetAndNoLink(): void
    {
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'deprecation' => ['date' => '2024-06-30'],
            ],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame(
            '@' . new DateTimeImmutable('2024-06-30T00:00:00+00:00')->getTimestamp(),
            $response->getHeaderLine('Deprecation'),
        );
        self::assertSame('', $response->getHeaderLine('Sunset'));
        self::assertSame('', $response->getHeaderLine('Link'));
    }

    public function testTheHandlersOwnDeprecationAnswerWinsOverTheDeclaration(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json', 'Deprecation' => '@1700000000'],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'deprecation' => ['date' => '2023-06-30T23:59:59Z'],
            ],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame('@1700000000', $response->getHeaderLine('Deprecation'));
        self::assertSame('', $response->getHeaderLine('Sunset'));
    }

    public function testTheHandlersOwnSunsetAnswerWinsOverTheDeclaration(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json', 'Sunset' => 'Wed, 01 Jan 2025 00:00:00 GMT'],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'deprecation' => [
                    'date' => '2023-06-30T23:59:59Z',
                    'sunset' => '2024-06-30T00:00:00Z',
                ],
            ],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame('@1688169599', $response->getHeaderLine('Deprecation'));
        self::assertSame('Wed, 01 Jan 2025 00:00:00 GMT', $response->getHeaderLine('Sunset'));
    }

    public function testADeclaredLinkIsAddedBesideTheHandlersOwnLinks(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                ['Content-Type' => 'application/json', 'Link' => '</profile>; rel="self"'],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'deprecation' => [
                    'date' => '2023-06-30T23:59:59Z',
                    'link' => 'https://example.com/deprecations/profile',
                ],
            ],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame(
            ['</profile>; rel="self"', '<https://example.com/deprecations/profile>; rel="deprecation"'],
            $response->getHeader('Link'),
        );
    }

    public function testTheSameDeprecationLinkIsNotDuplicated(): void
    {
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                [
                    'Content-Type' => 'application/json',
                    'Link' => '<https://example.com/deprecations/profile>; rel="deprecation"',
                ],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'deprecation' => [
                    'date' => '2023-06-30T23:59:59Z',
                    'link' => 'https://example.com/deprecations/profile',
                ],
            ],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame(
            ['<https://example.com/deprecations/profile>; rel="deprecation"'],
            $response->getHeader('Link'),
        );
    }

    public function testTheSameDeprecationLinkIsNotDuplicatedAcrossUnicodeCase(): void
    {
        // The de-duplication folds both sides with the Unicode case map:
        // the link's non-ASCII uppercase (E-acute) folds to its lowercase
        // spelling, so the handler's own answer and the declaration's
        // build of the same target match case-insensitively, and no
        // second Link is appended. An ASCII-only fold would leave the
        // accent uppercase on one side and answer a second header.
        $container = new Container();
        $container->set(
            'Profile@show',
            static fn (): callable => static fn (array $parameters): ResponseInterface => new Response(
                200,
                [
                    'Content-Type' => 'application/json',
                    'Link' => '</DÉPRECATIONS/legacy>; rel="deprecation"',
                ],
                'profile',
            ),
        );
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'deprecation' => [
                    'date' => '2023-06-30T23:59:59Z',
                    'link' => '/DÉPRECATIONS/legacy',
                ],
            ],
        ]);

        $response = $this->handler($router, $container)
            ->handle($this->request('GET', 'api.example.com', 'application/json'))
        ;

        self::assertSame(
            ['</DÉPRECATIONS/legacy>; rel="deprecation"'],
            $response->getHeader('Link'),
        );
    }

    public function testADeprecatedRouteAnswersItsHeadersOnANotModifiedResponse(): void
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
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'deprecation' => [
                    'date' => '2023-06-30T23:59:59Z',
                    'sunset' => '2024-06-30T00:00:00Z',
                    'link' => 'https://example.com/deprecations/profile',
                ],
            ],
        ]);

        $request = $this->request(
            'GET',
            'api.example.com',
            'application/json',
            extraHeaders: ['If-None-Match' => 'W/"profile-v1"'],
        );
        $response = $this->handler($router, $container)->handle($request);

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('@1688169599', $response->getHeaderLine('Deprecation'));
        self::assertSame('Sun, 30 Jun 2024 00:00:00 GMT', $response->getHeaderLine('Sunset'));
        self::assertSame(
            '<https://example.com/deprecations/profile>; rel="deprecation"',
            $response->getHeaderLine('Link'),
        );
        self::assertSame('', $response->getBody()->getContents());
    }

    public function testARefusalFromADeprecatedRouteAnswersNoDeprecationHeaders(): void
    {
        // The deprecation posture belongs to the route's representation,
        // not to its refusals: the boundary renders the typed refusal
        // family's own RFC 9457 posture, and the error boundary is not
        // the route's lifecycle billboard.
        $container = self::containerWithProfileHandler();
        $router = Router::fromRoutes([
            [
                'path' => '/profile',
                'method' => ['GET'],
                'handler' => 'Profile@show',
                'deprecation' => ['date' => '2023-06-30T23:59:59Z'],
            ],
        ]);

        try {
            $this->handler($router, $container)
                ->handle($this->request('POST', 'api.example.com', 'application/json'))
            ;
            self::fail('A method refusal must raise the typed family.');
        } catch (MethodNotAllowedException $methodNotAllowedException) {
            self::assertSame(['GET'], $methodNotAllowedException->allowedMethods);
        }
    }

    public function testTheMatchAnswersThroughTheTelemetryWhenTheConstructionCarriesOne(): void
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
        $inMemorySpanExporter = new InMemorySpanExporter();
        $tracer = new Tracer(new BatchSpanProcessor($inMemorySpanExporter), new TelemetryClock());
        $counterMeter = new CounterMeter();
        $handler = new RouterRequestHandler(
            $router,
            $container,
            new Psr17Factory(),
            new RouteMatchTelemetry($tracer, $counterMeter, new SteppingTelemetryClock()),
        );

        $response = $handler->handle($this->request('GET', 'api.example.com', 'application/json'));

        self::assertSame(200, $response->getStatusCode(), 'the observed match answers as the unobserved one does');

        $tracer->flush();
        $spans = $inMemorySpanExporter->spans();
        self::assertCount(1, $spans);

        $span = $spans[0];
        self::assertSame(RouteMatchTelemetry::MATCH_SPAN_NAME, $span->name);
        self::assertSame('GET', $span->attributes['http.request.method']);
        self::assertSame('/profile', $span->attributes['url.path']);
        self::assertSame(RouteMatchExplanation::OUTCOME_FOUND, $span->attributes['router.match.outcome']);

        $matches = $counterMeter->snapshot()[MeterSlot::key(RouteMatchTelemetry::MATCHES_TOTAL, [
            'http.request.method' => 'GET',
            'router.match.outcome' => RouteMatchExplanation::OUTCOME_FOUND,
        ])];
        self::assertSame(1, $matches['count']);
    }

    public function testTheMatchSpanJoinsTheSpanTheRequestCarries(): void
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
        $inMemorySpanExporter = new InMemorySpanExporter();
        $tracer = new Tracer(new BatchSpanProcessor($inMemorySpanExporter), new TelemetryClock());
        $handler = new RouterRequestHandler(
            $router,
            $container,
            new Psr17Factory(),
            new RouteMatchTelemetry($tracer, new CounterMeter(), new SteppingTelemetryClock()),
        );
        $requestSpan = $tracer->startSpan('zef.http.request', ['http.request.method' => 'GET']);

        $handler->handle(
            $this->request('GET', 'api.example.com', 'application/json')
                ->withAttribute(CorrelationMiddleware::SPAN_ATTRIBUTE, $requestSpan),
        );
        $requestSpan->end();
        $tracer->flush();

        $matchSpans = array_values(array_filter(
            $inMemorySpanExporter->spans(),
            static fn (SpanData $span): bool => $span->name === RouteMatchTelemetry::MATCH_SPAN_NAME,
        ));
        self::assertCount(1, $matchSpans);
        self::assertSame(
            $requestSpan->getContext()->traceId,
            $matchSpans[0]->context->traceId,
            'the match span joins the request span the correlation boundary exposed',
        );
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

        return new RouterRequestHandler($router, $container, $psr17Factory);
    }

    private function boundary(?RecordingLogger $recordingLogger = null): ErrorMappingMiddleware
    {
        return new ErrorMappingMiddleware($recordingLogger);
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
