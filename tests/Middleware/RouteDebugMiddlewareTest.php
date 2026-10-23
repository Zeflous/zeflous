<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\RouteDebugMiddleware;
use Zef\Framework\Router\Router;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The router observability family's debug disclosure, on its own
 * terms: the bounded explanation a routing-refusal response carries
 * in a development environment (each stage's own payload -- the
 * path-stage neighborhood, the allowed methods, the producible
 * media types), the precise attribution (a refusal that came from
 * past the router is never stamped with the router's name), the
 * double fail-closed gate (the statuses the disclosure answers, and
 * the environment verdict's own allowlist), the last-writer refusal
 * (an already-stamped header is never overwritten), and the
 * HEAD-over-GET fallback replayed on the explanation itself.
 *
 * @internal
 */
#[CoversClass(RouteDebugMiddleware::class)]
final class RouteDebugMiddlewareTest extends TestCase
{
    private false|string $previousEnvironment;

    #[Override]
    protected function setUp(): void
    {
        $this->previousEnvironment = getenv('APP_ENV');
        putenv('APP_ENV=development');
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->previousEnvironment === false) {
            putenv('APP_ENV');
        } else {
            putenv('APP_ENV=' . $this->previousEnvironment);
        }
    }

    public function testStampsTheExplanationOnANotFoundRefusal(): void
    {
        $response = $this->middleware()->process(
            $this->request('GET', '/userss'),
            new FixedResponseHandler($this->response(404)),
        );

        self::assertSame(
            'stage=path survivors=path:0,host:0,version:0 prefix=/users candidates=/users,/users/{id}',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
        );
    }

    public function testStampsTheAllowedMethodsOnAMethodRefusal(): void
    {
        $response = $this->middleware()->process(
            $this->request('DELETE', '/posts'),
            new FixedResponseHandler($this->response(405)),
        );

        self::assertSame(
            'stage=method survivors=path:1,host:1,version:1 allowed=POST',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
        );
    }

    public function testStampsTheProducibleMediaTypesOnANegotiationRefusal(): void
    {
        $response = $this->middleware()->process(
            $this->request('GET', '/j', 'text/html'),
            new FixedResponseHandler($this->response(406)),
        );

        self::assertSame(
            'stage=accept survivors=path:1,host:1,version:1 producible=application/json',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
        );
    }

    public function testStampsTheHostStageOnAHostMiss(): void
    {
        $response = $this->middleware()->process(
            $this->request('GET', '/a', '', 'other.example.com'),
            new FixedResponseHandler($this->response(404)),
        );

        self::assertSame(
            'stage=host survivors=path:1,host:0,version:0',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
        );
    }

    public function testStampsNothingWhenTheRouteTableMatched(): void
    {
        $response = $this->middleware()->process(
            $this->request('GET', '/users'),
            new FixedResponseHandler($this->response(404)),
        );

        self::assertSame('', $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER));
    }

    #[DataProvider('provideTheDisclosureAnswersOnlyTheRoutingRefusalStatusesCases')]
    public function testTheDisclosureAnswersOnlyTheRoutingRefusalStatuses(int $statusCode): void
    {
        $response = $this->middleware()->process(
            $this->request('GET', '/userss'),
            new FixedResponseHandler($this->response($statusCode)),
        );

        self::assertSame('', $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER));
    }

    /**
     * @return iterable<array{int}>
     */
    public static function provideTheDisclosureAnswersOnlyTheRoutingRefusalStatusesCases(): iterable
    {
        yield 'an ok answer' => [200];

        yield 'a server error' => [500];

        yield 'a not-modified answer' => [304];

        yield 'a bad request' => [400];
    }

    #[DataProvider('provideTheDisclosureAnswersOnlyDevelopmentEnvironmentsCases')]
    public function testTheDisclosureAnswersOnlyDevelopmentEnvironments(string $environment): void
    {
        putenv('APP_ENV=' . $environment);

        $response = $this->middleware()->process(
            $this->request('GET', '/userss'),
            new FixedResponseHandler($this->response(404)),
        );

        self::assertSame(
            '',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
            'the environment verdict gates the disclosure a second time, fail-closed',
        );
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideTheDisclosureAnswersOnlyDevelopmentEnvironmentsCases(): iterable
    {
        yield 'production' => ['production'];

        yield 'staging' => ['staging'];

        yield 'the empty spelling' => [''];
    }

    public function testAnUnsetEnvironmentGateAnswersNotDevelopment(): void
    {
        putenv('APP_ENV');

        $response = $this->middleware()->process(
            $this->request('GET', '/userss'),
            new FixedResponseHandler($this->response(404)),
        );

        self::assertSame('', $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER));
    }

    public function testAnAlreadyStampedHeaderIsNeverOverwritten(): void
    {
        $response = $this->middleware()->process(
            $this->request('GET', '/userss'),
            new FixedResponseHandler($this->response(404, [
                RouteDebugMiddleware::DEBUG_HEADER => 'the host application stamped its own',
            ])),
        );

        self::assertSame(
            'the host application stamped its own',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
        );
    }

    public function testAHeadPathMissIsExplainedAsThePathMiss(): void
    {
        $response = $this->middleware()->process(
            $this->request('HEAD', '/userss'),
            new FixedResponseHandler($this->response(404)),
        );

        self::assertSame(
            'stage=path survivors=path:0,host:0,version:0 prefix=/users candidates=/users,/users/{id}',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
            'a HEAD request the table has no path for is the path miss it is -- the fallback'
            . ' rule reads the explanation the matcher would have read',
        );
    }

    public function testANonHeadMethodRefusalIsExplainedAsTheRequestsOwnMethod(): void
    {
        $response = $this->middleware()->process(
            $this->request('DELETE', '/both'),
            new FixedResponseHandler($this->response(405)),
        );

        self::assertSame(
            'stage=method survivors=path:1,host:1,version:1 allowed=GET,POST',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
            'the fallback is the HEAD request\'s own rule -- a DELETE refusal is explained as the DELETE match',
        );
    }

    public function testAHeadRequestWhoseFallbackMatchesIsNotStamped(): void
    {
        $response = $this->middleware()->process(
            $this->request('HEAD', '/users'),
            new FixedResponseHandler($this->response(404)),
        );

        self::assertSame(
            '',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
            'the request matcher replays the HEAD-over-GET fallback, and so does the explanation',
        );
    }

    public function testAHeadRequestWhoseFallbackNegotiatesIsExplainedAsTheGetMatch(): void
    {
        $response = $this->middleware()->process(
            $this->request('HEAD', '/j', 'text/html'),
            new FixedResponseHandler($this->response(406)),
        );

        self::assertSame(
            'stage=accept survivors=path:1,host:1,version:1 producible=application/json',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
        );
    }

    public function testAHeadRefusalWithoutTheGetAnswerIsExplainedAsTheMethodRefusal(): void
    {
        $response = $this->middleware()->process(
            $this->request('HEAD', '/posts'),
            new FixedResponseHandler($this->response(405)),
        );

        self::assertSame(
            'stage=method survivors=path:1,host:1,version:1 allowed=POST',
            $response->getHeaderLine(RouteDebugMiddleware::DEBUG_HEADER),
        );
    }

    private function middleware(): RouteDebugMiddleware
    {
        return new RouteDebugMiddleware($this->router());
    }

    private function router(): Router
    {
        return Router::fromRoutes([
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'Users@show',
                'constraints' => ['id' => '\d+'],
            ],
            ['path' => '/posts', 'method' => ['POST'], 'handler' => 'Posts@store'],
            ['path' => '/both', 'method' => ['GET', 'POST'], 'handler' => 'Both@answer'],
            ['path' => '/a', 'method' => ['GET'], 'handler' => 'A@x', 'host' => 'api.example.com'],
            [
                'path' => '/j',
                'method' => ['GET'],
                'handler' => 'J@x',
                'produces' => ['application/json'],
            ],
        ]);
    }

    /**
     * @param array<string, string> $headers
     */
    private function response(int $statusCode, array $headers = []): ResponseInterface
    {
        return new Response($statusCode, $headers);
    }

    private function request(
        string $method,
        string $path,
        string $accept = '',
        string $host = 'api.example.com',
    ): ServerRequest {
        return new ServerRequest(
            $method,
            new Uri('https://' . $host . $path),
            headers: ['Host' => $host, 'Accept' => $accept],
        );
    }
}
