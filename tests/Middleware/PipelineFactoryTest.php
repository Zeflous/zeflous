<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\MiddlewareException;
use Zef\Framework\Middleware\PipelineFactory;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Middleware\EarlyReturnMiddleware;
use Zef\Test\Support\Middleware\MiddlewareTrace;
use Zef\Test\Support\Middleware\RecordingMiddleware;

/**
 * @internal
 */
#[CoversClass(PipelineFactory::class)]
#[CoversClass(MiddlewareException::class)]
final class PipelineFactoryTest extends TestCase
{
    public function testAMissingStackKeyBuildsTheTerminalAlone(): void
    {
        $fixedResponseHandler = new FixedResponseHandler(new Response(200, [], 'terminal only'));

        $pipeline = $this->factory(new Config([]), new Container(), $fixedResponseHandler)->build();

        $response = $pipeline->handle($this->request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('terminal only', (string) $response->getBody());
    }

    public function testAExplicitlyNullStackKeyBuildsTheTerminalAlone(): void
    {
        $fixedResponseHandler = new FixedResponseHandler(new Response(200));

        $pipeline = $this->factory(
            new Config(['middleware' => ['stack' => null]]),
            new Container(),
            $fixedResponseHandler,
        )->build();

        self::assertSame(200, $pipeline->handle($this->request())->getStatusCode());
    }

    public function testAnEmptyStackBuildsTheTerminalAlone(): void
    {
        $fixedResponseHandler = new FixedResponseHandler(new Response(200));

        $pipeline = $this->factory(
            new Config(['middleware' => ['stack' => []]]),
            new Container(),
            $fixedResponseHandler,
        )->build();

        self::assertSame(200, $pipeline->handle($this->request())->getStatusCode());
    }

    public function testANonListStackIsRejected(): void
    {
        try {
            $this->factory(
                new Config(['middleware' => ['stack' => 'nope']]),
                new Container(),
                new FixedResponseHandler(new Response(200)),
            )->build();
            self::fail('Expected a MiddlewareException to be thrown.');
        } catch (MiddlewareException $middlewareException) {
            self::assertSame(
                "The 'middleware.stack' configuration must be a list of middleware entries, string given.",
                $middlewareException->getMessage(),
            );
        }
    }

    public function testAMapAddressedStackIsRejected(): void
    {
        try {
            $this->factory(
                new Config(['middleware' => ['stack' => ['auth' => ['service' => 'app.auth']]]]),
                new Container(),
                new FixedResponseHandler(new Response(200)),
            )->build();
            self::fail('Expected a MiddlewareException to be thrown.');
        } catch (MiddlewareException $middlewareException) {
            self::assertSame(
                "The 'middleware.stack' configuration must be a list of middleware entries, array given.",
                $middlewareException->getMessage(),
            );
        }
    }

    public function testAnUnregisteredMiddlewareServiceIsRejected(): void
    {
        try {
            $this->factory(
                new Config(['middleware' => ['stack' => ['app.auth']]]),
                new Container(),
                new FixedResponseHandler(new Response(200)),
            )->build();
            self::fail('Expected a MiddlewareException to be thrown.');
        } catch (MiddlewareException $middlewareException) {
            self::assertSame(
                "Middleware service 'app.auth' is not registered.",
                $middlewareException->getMessage(),
            );
        }
    }

    public function testANonMiddlewareServiceIsRejected(): void
    {
        $container = new Container();
        $container->set('app.plain', static fn (): stdClass => new stdClass());

        try {
            $this->factory(
                new Config(['middleware' => ['stack' => ['app.plain']]]),
                $container,
                new FixedResponseHandler(new Response(200)),
            )->build();
            self::fail('Expected a MiddlewareException to be thrown.');
        } catch (MiddlewareException $middlewareException) {
            self::assertSame(
                "Service 'app.plain' does not implement MiddlewareInterface.",
                $middlewareException->getMessage(),
            );
        }
    }

    public function testTheStackExecutesInDeclarationOrderWithoutPriorities(): void
    {
        $middlewareTrace = new MiddlewareTrace();
        $container = $this->containerWithRecordingMiddleware(['first', 'second', 'third'], $middlewareTrace);

        $pipeline = $this->factory(
            new Config([
                'middleware' => [
                    'stack' => [
                        ['service' => 'mw.first'],
                        ['service' => 'mw.second'],
                        ['service' => 'mw.third'],
                    ],
                ],
            ]),
            $container,
            new FixedResponseHandler(new Response(200)),
        )->build();

        $pipeline->handle($this->request());

        self::assertSame(
            ['first:enter', 'second:enter', 'third:enter', 'third:exit', 'second:exit', 'first:exit'],
            $middlewareTrace->marks(),
        );
    }

    public function testPriorityOrdersHighestFirst(): void
    {
        $middlewareTrace = new MiddlewareTrace();
        $container = $this->containerWithRecordingMiddleware(['outer', 'inner'], $middlewareTrace);

        $pipeline = $this->factory(
            new Config([
                'middleware' => [
                    'stack' => [
                        ['service' => 'mw.inner', 'priority' => 100],
                        ['service' => 'mw.outer', 'priority' => 500],
                    ],
                ],
            ]),
            $container,
            new FixedResponseHandler(new Response(200)),
        )->build();

        $pipeline->handle($this->request());

        self::assertSame(
            ['outer:enter', 'inner:enter', 'inner:exit', 'outer:exit'],
            $middlewareTrace->marks(),
            'priority 500 runs outside priority 100 regardless of declaration order',
        );
    }

    public function testANumericStringPriorityOrdersToo(): void
    {
        $middlewareTrace = new MiddlewareTrace();
        $container = $this->containerWithRecordingMiddleware(['outer', 'inner'], $middlewareTrace);

        $pipeline = $this->factory(
            new Config([
                'middleware' => [
                    'stack' => [
                        ['service' => 'mw.inner', 'priority' => 100],
                        ['service' => 'mw.outer', 'priority' => '500'],
                    ],
                ],
            ]),
            $container,
            new FixedResponseHandler(new Response(200)),
        )->build();

        $pipeline->handle($this->request());

        self::assertSame(['outer:enter', 'inner:enter', 'inner:exit', 'outer:exit'], $middlewareTrace->marks());
    }

    public function testTiesKeepTheDeclarationOrder(): void
    {
        $middlewareTrace = new MiddlewareTrace();
        $container = $this->containerWithRecordingMiddleware(
            ['declared-first', 'declared-second', 'declared-third'],
            $middlewareTrace,
        );

        $pipeline = $this->factory(
            new Config([
                'middleware' => [
                    'stack' => [
                        ['service' => 'mw.declared-first', 'priority' => 200],
                        ['service' => 'mw.declared-second', 'priority' => 200],
                        ['service' => 'mw.declared-third', 'priority' => 200],
                    ],
                ],
            ]),
            $container,
            new FixedResponseHandler(new Response(200)),
        )->build();

        $pipeline->handle($this->request());

        self::assertSame(
            [
                'declared-first:enter',
                'declared-second:enter',
                'declared-third:enter',
                'declared-third:exit',
                'declared-second:exit',
                'declared-first:exit',
            ],
            $middlewareTrace->marks(),
        );
    }

    public function testMixedFormsComposeInOneStack(): void
    {
        $middlewareTrace = new MiddlewareTrace();
        $container = $this->containerWithRecordingMiddleware(['string', 'mapped'], $middlewareTrace);
        $container->set('mw.early', static fn (): EarlyReturnMiddleware => new EarlyReturnMiddleware(
            new Response(418, [], 'halted'),
        ));

        $pipeline = $this->factory(
            new Config([
                'middleware' => [
                    'stack' => [
                        'mw.mapped',
                        ['service' => 'mw.string'],
                        ['id' => 'mw.early', 'priority' => 900],
                    ],
                ],
            ]),
            $container,
            new FixedResponseHandler(new Response(200)),
        )->build();

        $response = $pipeline->handle($this->request());

        self::assertSame(418, $response->getStatusCode());
        self::assertSame('halted', (string) $response->getBody());
        self::assertSame(
            [],
            $middlewareTrace->marks(),
            'the highest-priority early return keeps the lower entries from running',
        );
    }

    private function factory(
        Config $config,
        Container $container,
        RequestHandlerInterface $requestHandler,
    ): PipelineFactory {
        return new PipelineFactory($container, $config, $requestHandler);
    }

    /**
     * @param list<string> $names
     */
    private function containerWithRecordingMiddleware(array $names, MiddlewareTrace $middlewareTrace): Container
    {
        $container = new Container();
        foreach ($names as $name) {
            $container->set(
                'mw.' . $name,
                static fn (): RecordingMiddleware => new RecordingMiddleware($name, $middlewareTrace),
            );
        }

        return $container;
    }

    private function request(): ServerRequest
    {
        return new ServerRequest('GET', new Uri('https://api.example.com/items'));
    }
}
