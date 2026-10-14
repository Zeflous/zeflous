<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\ContainerBootstrapper;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\MiddlewarePipeline;
use Zef\Framework\Middleware\PipelineFactory;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Middleware\EarlyReturnMiddleware;
use Zef\Test\Support\Middleware\MiddlewareTrace;
use Zef\Test\Support\Middleware\RecordingMiddleware;

/**
 * The middleware lane's hot-path reference suite -- the per-request costs
 * the pipeline pays in a persistent worker (ROADMAP.md, "Middleware
 * Pipeline" -> performance; the CI bench lane runs this file on every
 * push).
 *
 * Not a victory claim. The honest cost structure these numbers pin: the
 * execution path clones one pipeline stage per middleware per request (the
 * clone-on-write discipline -- an immutable `new self` advance, not an
 * index write), so `handle()` cost is linear in the stack depth with a
 * small constant; and the build path pays one parse, one stable priority
 * sort and one container resolution per declared middleware, exactly once
 * per worker. Each method guards its own invariant, so a broken hot path
 * fails the bench lane rather than silently reporting a number.
 */
final readonly class PipelineBench
{
    private ServerRequest $serverRequest;

    private Container $container;

    private Config $config;

    private MiddlewarePipeline $fiveDeep;

    private MiddlewarePipeline $earlyReturn;

    public function __construct()
    {
        $this->serverRequest = new ServerRequest('GET', new Uri('https://api.example.com/items'));

        $middlewareTrace = new MiddlewareTrace();
        $this->config = new Config([
            'services' => [
                'mw.a' => ['value' => new RecordingMiddleware('a', $middlewareTrace)],
                'mw.b' => ['value' => new RecordingMiddleware('b', $middlewareTrace)],
                'mw.c' => ['value' => new RecordingMiddleware('c', $middlewareTrace)],
                'mw.d' => ['value' => new RecordingMiddleware('d', $middlewareTrace)],
                'mw.e' => ['value' => new RecordingMiddleware('e', $middlewareTrace)],
            ],
            'middleware' => [
                'stack' => [
                    ['service' => 'mw.a', 'priority' => 500],
                    ['service' => 'mw.b', 'priority' => 400],
                    ['service' => 'mw.c', 'priority' => 300],
                    ['service' => 'mw.d', 'priority' => 200],
                    ['service' => 'mw.e', 'priority' => 100],
                ],
            ],
        ]);
        $this->container = new ContainerBootstrapper()->bootstrap($this->config, new Container());
        $this->fiveDeep = new PipelineFactory(
            $this->container,
            $this->config,
            new FixedResponseHandler(new Response(200)),
        )->build();

        $earlyContainer = new Container();
        $earlyContainer->set(
            'mw.early',
            static fn (): EarlyReturnMiddleware => new EarlyReturnMiddleware(new Response(200, [], 'halted')),
        );
        $this->earlyReturn = new PipelineFactory(
            $earlyContainer,
            new Config(['middleware' => ['stack' => [['service' => 'mw.early', 'priority' => 900]]]]),
            new FixedResponseHandler(new Response(200)),
        )->build();
    }

    #[Revs(2000)]
    public function benchHandleFiveDeep(): void
    {
        $response = $this->fiveDeep->handle($this->serverRequest);

        \assert($response->getStatusCode() === 200, 'the five-deep pipeline must answer the terminal response');
    }

    #[Revs(500)]
    public function benchBuildFiveMiddleware(): void
    {
        $middlewarePipeline = new PipelineFactory(
            $this->container,
            $this->config,
            new FixedResponseHandler(new Response(200)),
        )->build();

        \assert(
            $middlewarePipeline->handle($this->serverRequest)->getStatusCode() === 200,
            'the built pipeline must execute',
        );
    }

    #[Revs(3000)]
    public function benchEarlyReturnSkipsTheInnerStack(): void
    {
        $response = $this->earlyReturn->handle($this->serverRequest);

        \assert((string) $response->getBody() === 'halted', 'the early return must answer before the terminal');
    }
}
