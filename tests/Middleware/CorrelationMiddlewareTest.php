<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Zef\Framework\Http\CorrelationPolicy;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\CorrelationMiddleware;
use Zef\Framework\Middleware\CorrelationRequestSpan;
use Zef\Framework\Telemetry\BatchSpanProcessor;
use Zef\Framework\Telemetry\InMemorySpanExporter;
use Zef\Framework\Telemetry\NoopSpan;
use Zef\Framework\Telemetry\SpanData;
use Zef\Framework\Telemetry\SpanInterface;
use Zef\Framework\Telemetry\TraceContextPropagator;
use Zef\Framework\Telemetry\Tracer;
use Zef\Framework\Telemetry\TracerInterface;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\ThrowingHandler;
use Zef\Test\Support\Telemetry\SteppingTelemetryClock;

/**
 * The propagation boundary, on its own terms: the request span the
 * boundary opens under the inbound W3C trace-context (the fresh
 * trace when no parent is extractable), the request attribute the
 * span is exposed under, the answer's record and the failure's
 * record, the finally-end either way, the echo's disclosure (armed,
 * and its void-context refusal over a disarmed tracer), and the
 * disabled family's pass-through -- the campaign's third family,
 * proven over the real tracer the factory wires it to.
 *
 * @internal
 */
#[CoversClass(CorrelationMiddleware::class)]
#[CoversClass(CorrelationRequestSpan::class)]
#[CoversClass(CorrelationPolicy::class)]
#[CoversClass(TraceContextPropagator::class)]
final class CorrelationMiddlewareTest extends TestCase
{
    private const string PARENT_TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string PARENT_SPAN_ID = '00f067aa0ba902b7';

    private const string PARENT_TRACEPARENT = '00-' . self::PARENT_TRACE_ID . '-' . self::PARENT_SPAN_ID . '-01';

    private InMemorySpanExporter $inMemorySpanExporter;

    private BatchSpanProcessor $batchSpanProcessor;

    private SteppingTelemetryClock $steppingTelemetryClock;

    #[Override]
    protected function setUp(): void
    {
        $this->inMemorySpanExporter = new InMemorySpanExporter();
        $this->batchSpanProcessor = new BatchSpanProcessor($this->inMemorySpanExporter);
        $this->steppingTelemetryClock = new SteppingTelemetryClock(
            monotonicEpoch: 1_000,
            monotonicStep: 5_000,
            wallEpoch: 1_000_000_000,
            wallStep: 1_000,
        );
    }

    public function testTheDisabledFamilyIsAPassThrough(): void
    {
        $request = $this->request(self::PARENT_TRACEPARENT);

        $response = $this->middleware(enabled: false, echo: false)
            ->process($request, new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('answered', (string) $response->getBody());
        $this->flush();
        $spans = $this->inMemorySpanExporter->spans();
        self::assertSame(
            [],
            $spans,
            'no span was opened, even after the flush forces delivery',
        );
        self::assertNull(
            $request->getAttribute(CorrelationMiddleware::SPAN_ATTRIBUTE),
            'the pass-through request carries no span',
        );
    }

    public function testTheArmedBoundaryOpensTheRequestSpanUnderTheInboundParent(): void
    {
        $response = $this->middleware(enabled: true, echo: false)
            ->process(
                $this->request(self::PARENT_TRACEPARENT),
                new FixedResponseHandler(new Response(200, [], 'answered')),
            )
        ;

        self::assertSame(200, $response->getStatusCode());
        $this->flush();

        $record = $this->theRequestRecord();
        self::assertSame('zef.http.request', $record->name);
        self::assertSame(self::PARENT_TRACE_ID, $record->context->traceId, 'the child inherits the parent trace');
        self::assertNotSame(self::PARENT_SPAN_ID, $record->context->spanId, 'the child mints its own span id');
        self::assertNotNull($record->parent, 'the inbound pair parents the span');
        self::assertSame(self::PARENT_TRACE_ID, $record->parent->traceId);
        self::assertSame(self::PARENT_SPAN_ID, $record->parent->spanId);
        self::assertSame('GET', $record->attributes['http.request.method']);
        self::assertSame('/orders', $record->attributes['url.path']);
        self::assertSame('app.example.com', $record->attributes['server.address']);
    }

    public function testTheTraceStateCompanionParentsTheSpanUntouched(): void
    {
        $this->middleware(enabled: true, echo: false)
            ->process(
                $this->request(self::PARENT_TRACEPARENT, 'vendor=alpha,other=beta'),
                new FixedResponseHandler(new Response(200)),
            )
        ;

        $this->flush();

        $record = $this->theRequestRecord();
        self::assertNotNull($record->parent, 'the companion rides on the parent');
        self::assertSame(
            'vendor=alpha,other=beta',
            $record->parent->traceState,
            'the inbound companion rides whole into the parent context',
        );
    }

    public function testAnAbsentCompanionParentsANullTraceState(): void
    {
        $this->middleware(enabled: true, echo: false)
            ->process(
                $this->request(self::PARENT_TRACEPARENT),
                new FixedResponseHandler(new Response(200)),
            )
        ;

        $this->flush();

        $record = $this->theRequestRecord();
        self::assertNotNull($record->parent, 'the inbound parent still parents the span');
        self::assertNull(
            $record->parent->traceState,
            'the absent companion is the null the propagation pair answers, never an empty line',
        );
    }

    public function testAMalformedParentMintsAFreshTrace(): void
    {
        $this->middleware(enabled: true, echo: false)
            ->process(
                $this->request('not-a-traceparent'),
                new FixedResponseHandler(new Response(200)),
            )
        ;

        $this->flush();

        $record = $this->theRequestRecord();
        self::assertNull($record->parent, 'a refused parent is no parent at all');
        self::assertNotSame(self::PARENT_TRACE_ID, $record->context->traceId);
    }

    public function testAnAbsentParentMintsAFreshTrace(): void
    {
        $this->middleware(enabled: true, echo: false)
            ->process($this->request(null), new FixedResponseHandler(new Response(200)))
        ;

        $this->flush();

        self::assertNull($this->theRequestRecord()->parent);
    }

    public function testTheSpanIsExposedOnTheRequestForEnrichment(): void
    {
        $enrichingHandler = new class implements RequestHandlerInterface {
            public ?SpanInterface $exposedSpan = null;

            #[Override]
            public function handle(PsrServerRequestInterface $psrServerRequest): PsrResponseInterface
            {
                $span = $psrServerRequest->getAttribute(CorrelationMiddleware::SPAN_ATTRIBUTE);
                Assert::assertInstanceOf(SpanInterface::class, $span);
                $this->exposedSpan = $span;
                $span->setAttribute('handler.touched', true);

                return new Response(201, [], 'created');
            }
        };

        $response = $this->middleware(enabled: true, echo: false)
            ->process($this->request(self::PARENT_TRACEPARENT), $enrichingHandler)
        ;

        self::assertSame(201, $response->getStatusCode());
        $this->flush();

        $record = $this->theRequestRecord();
        self::assertTrue($record->attributes['handler.touched'], 'the exposed span is the span that records');
        self::assertSame(201, $record->attributes['http.response.status_code']);
        self::assertSame('OK', $record->status);
    }

    public function testTheAnswerIsRecordedOnTheSpan(): void
    {
        $this->middleware(enabled: true, echo: false)
            ->process($this->request(self::PARENT_TRACEPARENT), new FixedResponseHandler(new Response(200)))
        ;
        $this->flush();
        $ok = $this->theRequestRecord();
        self::assertSame(200, $ok->attributes['http.response.status_code']);
        self::assertSame('OK', $ok->status);

        $this->inMemorySpanExporter->reset();

        $this->middleware(enabled: true, echo: false)
            ->process($this->request(self::PARENT_TRACEPARENT), new FixedResponseHandler(new Response(500)))
        ;
        $this->flush();
        $boundary = $this->theRequestRecord();
        self::assertSame(500, $boundary->attributes['http.response.status_code']);
        self::assertSame('ERROR', $boundary->status, 'the 5xx boundary itself folds to the error status');

        $this->inMemorySpanExporter->reset();

        $this->middleware(enabled: true, echo: false)
            ->process($this->request(self::PARENT_TRACEPARENT), new FixedResponseHandler(new Response(503)))
        ;
        $this->flush();
        $error = $this->theRequestRecord();
        self::assertSame(503, $error->attributes['http.response.status_code']);
        self::assertSame('ERROR', $error->status, 'a 5xx answer folds to the error status');
    }

    public function testTheEchoDisclosesTheRequestSpansOwnIdentity(): void
    {
        $response = $this->middleware(enabled: true, echo: true)
            ->process($this->request(self::PARENT_TRACEPARENT), new FixedResponseHandler(new Response(200)))
        ;

        $this->flush();

        self::assertSame(
            $this->theRequestRecord()->context->traceParent(),
            $response->getHeaderLine('traceparent'),
            "the echo carries the server span's own identity, not the client's",
        );
        self::assertNotSame(self::PARENT_TRACEPARENT, $response->getHeaderLine('traceparent'));
    }

    public function testTheEchoStaysSilentWhenTheDisclosureShipsOff(): void
    {
        $response = $this->middleware(enabled: true, echo: false)
            ->process($this->request(self::PARENT_TRACEPARENT), new FixedResponseHandler(new Response(200)))
        ;

        self::assertSame('', $response->getHeaderLine('traceparent'));
    }

    public function testTheEchoRefusesTheVoidContextOfADisarmedTracer(): void
    {
        $correlationMiddleware = new CorrelationMiddleware(
            CorrelationPolicy::fromMap(['enabled' => true, 'echo_traceparent' => true]),
            $this->disarmedTracer(),
        );

        $response = $correlationMiddleware->process(
            $this->request(self::PARENT_TRACEPARENT),
            new FixedResponseHandler(new Response(200)),
        );

        self::assertSame(
            '',
            $response->getHeaderLine('traceparent'),
            'the void context must never stamp a response identity',
        );
    }

    public function testTheArmedBoundaryOverADisarmedTracerIsTheVoidPath(): void
    {
        $correlationMiddleware = new CorrelationMiddleware(
            CorrelationPolicy::fromMap(['enabled' => true, 'echo_traceparent' => false]),
            $this->disarmedTracer(),
        );

        $enrichingHandler = new class implements RequestHandlerInterface {
            #[Override]
            public function handle(PsrServerRequestInterface $psrServerRequest): PsrResponseInterface
            {
                $span = $psrServerRequest->getAttribute(CorrelationMiddleware::SPAN_ATTRIBUTE);
                Assert::assertInstanceOf(SpanInterface::class, $span);

                return new Response(200, [], $span instanceof NoopSpan ? 'void' : 'live');
            }
        };

        $response = $correlationMiddleware->process(
            $this->request(self::PARENT_TRACEPARENT),
            $enrichingHandler,
        );

        self::assertSame('void', (string) $response->getBody(), 'the code path is the same either way');
    }

    public function testAThrowingHandlerRecordsTheFailureAndPropagatesUntouched(): void
    {
        $exception = new RuntimeException('the handler failed');

        try {
            $this->middleware(enabled: true, echo: true)->process(
                $this->request(self::PARENT_TRACEPARENT),
                new ThrowingHandler($exception),
            );
            self::fail("The handler's failure must propagate untouched.");
        } catch (RuntimeException $runtimeException) {
            self::assertSame($exception, $runtimeException);
        }

        $this->flush();

        $record = $this->theRequestRecord();
        self::assertSame('ERROR', $record->status);
        self::assertSame(RuntimeException::class, $record->statusDescription);
        self::assertNotNull($record->events[0] ?? null, 'the failure is an event on the span');
    }

    public function testTheFailedSpanCarriesTheExceptionEventClampedByTheHygieneCensus(): void
    {
        try {
            $this->middleware(enabled: true, echo: false)->process(
                $this->request(self::PARENT_TRACEPARENT),
                new ThrowingHandler(new RuntimeException('boom-message')),
            );
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        $this->flush();

        $record = $this->theRequestRecord();
        $event = $record->events[0] ?? null;
        self::assertNotNull($event, 'the failure is an event on the span');
        self::assertSame('exception', $event['name']);
        self::assertSame(RuntimeException::class, $event['attributes']['exception.type']);
        self::assertSame('boom-message', $event['attributes']['exception.message']);
    }

    public function testAnOverLongFailureMessageIsBoundedBeforeTheQueue(): void
    {
        // The verbatim era the correlation family's own roadmap row
        // documented is over: the recording points the request span
        // writes through clamp every value to its payload budget,
        // so a failure text the handler spelled with three thousand
        // bytes arrives at the queue already bounded.
        try {
            $this->middleware(enabled: true, echo: false)->process(
                $this->request(self::PARENT_TRACEPARENT),
                new ThrowingHandler(new RuntimeException(str_repeat('x', 3000))),
            );
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        $this->flush();

        $event = $this->theRequestRecord()->events[0] ?? null;
        self::assertNotNull($event);
        self::assertSame(
            str_repeat('x', 2045) . '…',
            $event['attributes']['exception.message'],
            'the failure message answers the recording points own budget',
        );
    }

    public function testTheFlushPerRequestCadenceDrainsAtTheRequestsOwnEnd(): void
    {
        // The drain cadence is the export family's third fail-closed
        // knob, and the boundary is the one place it lands: an armed
        // cadence flushes the tracer at the request's own end, in the
        // finally that closes the span -- the delivery's own latency
        // inside the request path is the deployment's decision, and
        // this test spells it with the drain already observed, not
        // the later manual flush the other tests read.
        $correlationMiddleware = new CorrelationMiddleware(
            CorrelationPolicy::fromMap(['enabled' => true, 'echo_traceparent' => false]),
            $this->armedTracer(),
            flushPerRequest: true,
        );

        $correlationMiddleware->process(
            $this->request(self::PARENT_TRACEPARENT),
            new FixedResponseHandler(new Response(200)),
        );

        self::assertCount(
            1,
            $this->inMemorySpanExporter->spans(),
            'the request its own end already drained -- no later flush was called',
        );
    }

    public function testTheShippedCadenceLeavesTheDrainToTheDeployment(): void
    {
        // The two-argument construction the family's own shipped
        // posture spells: the cadence knob's default is the OFF the
        // export family declared, so the span sits in the queue and
        // the drain belongs to the deployment -- never to a default
        // the boundary took on the host's behalf.
        $correlationMiddleware = new CorrelationMiddleware(
            CorrelationPolicy::fromMap(['enabled' => true, 'echo_traceparent' => false]),
            $this->armedTracer(),
        );

        $correlationMiddleware->process(
            $this->request(self::PARENT_TRACEPARENT),
            new FixedResponseHandler(new Response(200)),
        );

        self::assertSame(
            [],
            $this->inMemorySpanExporter->spans(),
            'the shipped cadence holds the span in the queue -- the drain is the deployments own',
        );
    }

    public function testTheSpanEndsExactlyOnceEitherWay(): void
    {
        $this->middleware(enabled: true, echo: false)
            ->process($this->request(self::PARENT_TRACEPARENT), new FixedResponseHandler(new Response(200)))
        ;

        $this->flush();

        self::assertCount(1, $this->inMemorySpanExporter->spans(), 'the answer path ends its span');
    }

    public function testAMultiValueHeaderDegradesToOneJudgedLine(): void
    {
        $repeated = '00-' . self::PARENT_TRACE_ID . '-' . self::PARENT_SPAN_ID . '-01';

        $message = new ServerRequest('GET', new Uri('https://app.example.com/orders'))
            ->withHeader('traceparent', [$repeated, 'ignored-second'])
        ;
        self::assertInstanceOf(ServerRequest::class, $message);
        $request = $message;

        $this->middleware(enabled: true, echo: false)
            ->process($request, new FixedResponseHandler(new Response(200)))
        ;

        $this->flush();

        $record = $this->theRequestRecord();

        self::assertNull(
            $record->parent,
            'a multi-value header joins to one line the grammar refuses'
            . ' -- the W3C posture for a repeated traceparent',
        );
        self::assertNotSame(self::PARENT_TRACE_ID, $record->context->traceId, 'the refusal mints a fresh trace');
    }

    private function middleware(bool $enabled, bool $echo): CorrelationMiddleware
    {
        return new CorrelationMiddleware(
            CorrelationPolicy::fromMap(['enabled' => $enabled, 'echo_traceparent' => $echo]),
            $this->armedTracer(),
        );
    }

    private function armedTracer(): TracerInterface
    {
        return new Tracer($this->batchSpanProcessor, $this->steppingTelemetryClock, true);
    }

    private function disarmedTracer(): TracerInterface
    {
        return new Tracer($this->batchSpanProcessor, $this->steppingTelemetryClock, false);
    }

    private function request(?string $traceParent, ?string $traceState = null): ServerRequest
    {
        $request = new ServerRequest('GET', new Uri('https://app.example.com/orders'));

        if ($traceParent !== null) {
            $request = $this->withHeader($request, 'traceparent', $traceParent);
        }

        if ($traceState !== null) {
            return $this->withHeader($request, 'tracestate', $traceState);
        }

        return $request;
    }

    /**
     * The withHeader() chain the PSR-7 interface types as
     * MessageInterface; the invariant every chain upholds is the
     * request's own type, so the helper asserts it back.
     */
    private function withHeader(ServerRequest $serverRequest, string $name, string $value): ServerRequest
    {
        $message = $serverRequest->withHeader($name, $value);
        self::assertInstanceOf(ServerRequest::class, $message);

        return $message;
    }

    private function flush(): void
    {
        $this->batchSpanProcessor->flush();
    }

    private function theRequestRecord(): SpanData
    {
        $spans = $this->inMemorySpanExporter->spans();

        self::assertNotSame([], $spans, 'the request span was recorded');

        return $spans[0];
    }
}
