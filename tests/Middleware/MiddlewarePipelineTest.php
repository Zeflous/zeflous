<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\MiddlewarePipeline;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Middleware\EarlyReturnMiddleware;
use Zef\Test\Support\Middleware\MiddlewareTrace;
use Zef\Test\Support\Middleware\RecordingMiddleware;

/**
 * @internal
 */
#[CoversClass(MiddlewarePipeline::class)]
#[CoversClass(Response::class)]
final class MiddlewarePipelineTest extends TestCase
{
    public function testAnEmptyPipelineAnswersTheTerminalHandler(): void
    {
        $fixedResponseHandler = new FixedResponseHandler(new Response(200, [], 'from the terminal'));

        $response = new MiddlewarePipeline([], $fixedResponseHandler)->handle($this->request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('from the terminal', (string) $response->getBody());
    }

    public function testAPipelineWithoutATerminalRefusesThroughTheInvariantSignal(): void
    {
        try {
            new MiddlewarePipeline()->handle($this->request());
            self::fail('a pipeline built without a terminal refuses instead of answering');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'The middleware pipeline was built without a terminal request handler.',
                $invariantViolationException->getMessage(),
            );
        }
    }

    public function testAnExhaustedStackWithoutATerminalRefusesThroughTheInvariantSignal(): void
    {
        $middlewareTrace = new MiddlewareTrace();
        $recordingMiddleware = new RecordingMiddleware('delegating', $middlewareTrace);

        $middlewarePipeline = new MiddlewarePipeline([$recordingMiddleware]);

        try {
            $middlewarePipeline->handle($this->request());
            self::fail('the exhausted stack without a terminal refuses instead of answering');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'The middleware pipeline was built without a terminal request handler.',
                $invariantViolationException->getMessage(),
            );
        }

        self::assertSame(
            ['delegating:enter'],
            $middlewareTrace->marks(),
            'the invariant refusal unwinds the onion like any fault: enter marks stay, exits are skipped',
        );
    }

    public function testMiddlewareRunsOutsideInAndBack(): void
    {
        $middlewareTrace = new MiddlewareTrace();
        $fixedResponseHandler = new FixedResponseHandler(new Response(200, [], 'done'));

        $middlewarePipeline = new MiddlewarePipeline(
            [
                new RecordingMiddleware('outer', $middlewareTrace),
                new RecordingMiddleware('inner', $middlewareTrace),
            ],
            $fixedResponseHandler,
        );

        $response = $middlewarePipeline->handle($this->request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['outer:enter', 'inner:enter', 'inner:exit', 'outer:exit'],
            $middlewareTrace->marks(),
            'the pipeline runs the onion: outer -> inner -> terminal -> inner -> outer',
        );
    }

    public function testAnEarlyReturningOuterMiddlewareSkipsTheInnerChain(): void
    {
        $middlewareTrace = new MiddlewareTrace();

        $middlewarePipeline = new MiddlewarePipeline(
            [
                new EarlyReturnMiddleware(new Response(418, [], 'short-circuit')),
                new RecordingMiddleware('inner', $middlewareTrace),
            ],
            new FixedResponseHandler(new Response(200, [], 'never')),
        );

        $response = $middlewarePipeline->handle($this->request());

        self::assertSame(418, $response->getStatusCode());
        self::assertSame('short-circuit', (string) $response->getBody());
        self::assertSame([], $middlewareTrace->marks(), 'nothing behind the early return runs');
    }

    public function testAPipelineIsReusableAcrossRequests(): void
    {
        $middlewareTrace = new MiddlewareTrace();
        $middlewarePipeline = new MiddlewarePipeline(
            [new RecordingMiddleware('only', $middlewareTrace)],
            new FixedResponseHandler(new Response(200)),
        );

        $first = $middlewarePipeline->handle($this->request());
        $second = $middlewarePipeline->handle($this->request());

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());
        self::assertSame(['only:enter', 'only:exit', 'only:enter', 'only:exit'], $middlewareTrace->marks());
    }

    public function testWithMiddlewareAppendsWithoutMutatingTheOriginal(): void
    {
        $middlewareTrace = new MiddlewareTrace();
        $fixedResponseHandler = new FixedResponseHandler(new Response(200));

        $middlewarePipeline = new MiddlewarePipeline(
            [new RecordingMiddleware('first', $middlewareTrace)],
            $fixedResponseHandler,
        );
        $double = $middlewarePipeline->withMiddleware(new RecordingMiddleware('second', $middlewareTrace));

        $middlewarePipeline->handle($this->request());

        self::assertSame(
            ['first:enter', 'first:exit'],
            $middlewareTrace->marks(),
            'the original pipeline still runs one middleware',
        );

        $double->handle($this->request());

        self::assertSame(
            ['first:enter', 'first:exit', 'first:enter', 'second:enter', 'second:exit', 'first:exit'],
            $middlewareTrace->marks(),
            'the derived pipeline runs both, in append order',
        );
    }

    private function request(): ServerRequest
    {
        return new ServerRequest('GET', new Uri('https://api.example.com/items'));
    }
}
