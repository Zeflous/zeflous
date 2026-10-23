<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteMatchExplanation;
use Zef\Framework\Middleware\RouteMatchTelemetry;
use Zef\Framework\Middleware\ServerRequestMetadata;
use Zef\Framework\Telemetry\BatchSpanProcessor;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\InMemorySpanExporter;
use Zef\Framework\Telemetry\MeterInterface;
use Zef\Framework\Telemetry\MeterSlot;
use Zef\Framework\Telemetry\NoopTracer;
use Zef\Framework\Telemetry\SpanData;
use Zef\Framework\Telemetry\TelemetryClock;
use Zef\Framework\Telemetry\Tracer;
use Zef\Test\Support\Telemetry\SteppingTelemetryClock;

/**
 * The observed route match, on its own terms: the match span's own
 * lifecycle (the opening routing surface, the outcome and route-name
 * attributes, the status each outcome folds to, the failure's
 * record-and-propagate), the parentage under the request span a
 * correlation boundary exposed (and the fresh trace without one),
 * the two metric series in their seconds domain and their two
 * allowed slices, and the two containment contracts -- a failing
 * meter never replaces the match's answer, and a failing match
 * propagates exactly as it arrived.
 *
 * @internal
 */
#[CoversClass(RouteMatchTelemetry::class)]
final class RouteMatchTelemetryTest extends TestCase
{
    public function testObservesAFoundMatchThroughBothEngines(): void
    {
        $inMemorySpanExporter = new InMemorySpanExporter();
        $tracer = new Tracer(new BatchSpanProcessor($inMemorySpanExporter), new TelemetryClock());
        $counterMeter = new CounterMeter();
        $steppingTelemetryClock = new SteppingTelemetryClock(
            monotonicEpoch: 0,
            monotonicStep: 500_000_000,
        );
        $routeMatchTelemetry = new RouteMatchTelemetry($tracer, $counterMeter, $steppingTelemetryClock);

        $matchResult = $routeMatchTelemetry->observed(
            null,
            self::metadata('GET', '/users/42', 'api.example.com'),
            static fn (): MatchResult => MatchResult::found(self::route('/users/{id}', 'users.show'), ['id' => '42']),
        );

        self::assertTrue($matchResult->isFound(), 'the observation answers the match\'s own result');

        $tracer->flush();
        $spans = $inMemorySpanExporter->spans();
        self::assertCount(1, $spans, 'one match span was opened, ended and delivered');

        $span = $spans[0];
        \assert($span instanceof SpanData);
        self::assertSame(RouteMatchTelemetry::MATCH_SPAN_NAME, $span->name);
        self::assertSame('OK', $span->status);
        self::assertNull($span->statusDescription);
        self::assertNull($span->parent, 'no boundary carried a request span, so the match span mints its own trace');
        self::assertTrue($span->context->isValid());
        self::assertSame([
            'http.request.method' => 'GET',
            'url.path' => '/users/42',
            'server.address' => 'api.example.com',
        ], array_intersect_key($span->attributes, [
            'http.request.method' => true,
            'url.path' => true,
            'server.address' => true,
        ]));
        self::assertSame(RouteMatchExplanation::OUTCOME_FOUND, $span->attributes['router.match.outcome']);
        self::assertSame('users.show', $span->attributes['router.route.name']);

        $dimensions = [
            'http.request.method' => 'GET',
            'router.match.outcome' => RouteMatchExplanation::OUTCOME_FOUND,
        ];
        $matches = $counterMeter->snapshot()[MeterSlot::key(RouteMatchTelemetry::MATCHES_TOTAL, $dimensions)];
        self::assertSame(1, $matches['count']);
        self::assertSame(1.0, $matches['sum']);

        $duration = $counterMeter->snapshot()[
            MeterSlot::key(RouteMatchTelemetry::MATCH_DURATION_SECONDS, $dimensions)
        ];
        self::assertSame(1, $duration['count']);
        self::assertSame(0.5, $duration['sum'], 'the frozen clock\'s step is the exact duration, in seconds');
    }

    public function testAHostlessRequestOmitsTheServerAddressAttribute(): void
    {
        [$routeMatchTelemetry, $inMemorySpanExporter, $tracer] = self::telemetry();

        $routeMatchTelemetry->observed(
            null,
            self::metadata('GET', '/users'),
            static fn (): MatchResult => MatchResult::found(self::route('/users'), []),
        );
        $tracer->flush();

        $span = $inMemorySpanExporter->spans()[0];
        \assert($span instanceof SpanData);
        self::assertArrayNotHasKey('server.address', $span->attributes);
    }

    public function testAFoundMatchWithoutARouteNameOmitsTheRouteNameAttribute(): void
    {
        [$routeMatchTelemetry, $inMemorySpanExporter, $tracer] = self::telemetry();

        $routeMatchTelemetry->observed(
            null,
            self::metadata('GET', '/users'),
            static fn (): MatchResult => MatchResult::found(self::route('/users'), []),
        );
        $tracer->flush();

        $span = $inMemorySpanExporter->spans()[0];
        \assert($span instanceof SpanData);
        self::assertArrayNotHasKey('router.route.name', $span->attributes);
    }

    public function testObservesAMethodNotAllowedRefusalWithTheErrorStatus(): void
    {
        [$routeMatchTelemetry, $inMemorySpanExporter, $tracer, $counterMeter] = self::telemetry();

        $routeMatchTelemetry->observed(
            null,
            self::metadata('DELETE', '/posts'),
            static fn (): MatchResult => MatchResult::methodNotAllowed(['GET', 'POST']),
        );
        $tracer->flush();

        $span = $inMemorySpanExporter->spans()[0];
        \assert($span instanceof SpanData);
        self::assertSame('ERROR', $span->status);
        self::assertSame(RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED, $span->statusDescription);
        self::assertSame(
            RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED,
            $span->attributes['router.match.outcome'],
        );
        self::assertArrayNotHasKey('router.route.name', $span->attributes);

        $matches = $counterMeter->snapshot()[MeterSlot::key(RouteMatchTelemetry::MATCHES_TOTAL, [
            'http.request.method' => 'DELETE',
            'router.match.outcome' => RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED,
        ])];
        self::assertSame(1, $matches['count']);
    }

    public function testObservesANotAcceptableRefusalWithTheErrorStatus(): void
    {
        [$routeMatchTelemetry, $inMemorySpanExporter, $tracer] = self::telemetry();

        $routeMatchTelemetry->observed(
            null,
            self::metadata('GET', '/j'),
            static fn (): MatchResult => MatchResult::notAcceptable(['application/json']),
        );
        $tracer->flush();

        $span = $inMemorySpanExporter->spans()[0];
        \assert($span instanceof SpanData);
        self::assertSame('ERROR', $span->status);
        self::assertSame(RouteMatchExplanation::OUTCOME_NOT_ACCEPTABLE, $span->statusDescription);
        self::assertSame(
            RouteMatchExplanation::OUTCOME_NOT_ACCEPTABLE,
            $span->attributes['router.match.outcome'],
        );
    }

    public function testObservesANotFoundMissWithTheUnsetStatus(): void
    {
        [$routeMatchTelemetry, $inMemorySpanExporter, $tracer, $counterMeter] = self::telemetry();

        $routeMatchTelemetry->observed(
            null,
            self::metadata('GET', '/nowhere'),
            static fn (): MatchResult => MatchResult::notFound(),
        );
        $tracer->flush();

        $span = $inMemorySpanExporter->spans()[0];
        \assert($span instanceof SpanData);
        self::assertSame('UNSET', $span->status, 'a not-found is the void answer, neither success nor failure');
        self::assertNull($span->statusDescription);
        self::assertSame(RouteMatchExplanation::OUTCOME_NOT_FOUND, $span->attributes['router.match.outcome']);

        $matches = $counterMeter->snapshot()[MeterSlot::key(RouteMatchTelemetry::MATCHES_TOTAL, [
            'http.request.method' => 'GET',
            'router.match.outcome' => RouteMatchExplanation::OUTCOME_NOT_FOUND,
        ])];
        self::assertSame(1, $matches['count']);
    }

    public function testTheSpanJoinsTheRequestSpansTraceWhenTheBoundaryExposedOne(): void
    {
        [$routeMatchTelemetry, $inMemorySpanExporter, $tracer] = self::telemetry();

        $requestSpan = $tracer->startSpan('zef.http.request', ['http.request.method' => 'GET']);
        $routeMatchTelemetry->observed(
            $requestSpan,
            self::metadata('GET', '/users'),
            static fn (): MatchResult => MatchResult::found(self::route('/users'), []),
        );
        $requestSpan->end();
        $tracer->flush();

        $matchSpans = array_values(array_filter(
            $inMemorySpanExporter->spans(),
            static fn (SpanData $span): bool => $span->name === RouteMatchTelemetry::MATCH_SPAN_NAME,
        ));
        self::assertCount(1, $matchSpans);

        $matchSpan = $matchSpans[0];
        self::assertSame($requestSpan->getContext()->traceId, $matchSpan->context->traceId);
        self::assertSame($requestSpan->getContext()->spanId, $matchSpan->parent?->spanId);
    }

    public function testAFailingMatchRecordsTheFailureAndPropagatesIt(): void
    {
        [$routeMatchTelemetry, $inMemorySpanExporter, $tracer, $counterMeter] = self::telemetry();

        try {
            $routeMatchTelemetry->observed(
                null,
                self::metadata('GET', '/users'),
                static function (): never {
                    throw new RuntimeException('the engine refused');
                },
            );
            self::fail('the observation propagates the match\'s own failure');
        } catch (RuntimeException $caught) {
            self::assertSame('the engine refused', $caught->getMessage());
        }

        $tracer->flush();
        $spans = $inMemorySpanExporter->spans();
        self::assertCount(1, $spans, 'the failed match still ends its span');

        $span = $spans[0];
        \assert($span instanceof SpanData);
        self::assertSame('ERROR', $span->status);
        self::assertSame(RuntimeException::class, $span->statusDescription);
        self::assertSame(RuntimeException::class, $span->events[0]['attributes']['exception.type']);
        self::assertSame('the engine refused', $span->events[0]['attributes']['exception.message']);
        self::assertSame([], $counterMeter->snapshot(), 'a failed match records no series');
    }

    public function testAFailingMeterNeverReplacesTheMatchAnswer(): void
    {
        $routeMatchTelemetry = new RouteMatchTelemetry(
            new NoopTracer(),
            self::failingMeter(),
            new SteppingTelemetryClock(),
        );

        $matchResult = $routeMatchTelemetry->observed(
            null,
            self::metadata('GET', '/users'),
            static fn (): MatchResult => MatchResult::found(self::route('/users'), []),
        );

        self::assertTrue($matchResult->isFound(), 'the measurement is best-effort, never the answer');
    }

    public function testADisarmedTracerAnswersTheVoidSpanAndTheMeterStillRecords(): void
    {
        $counterMeter = new CounterMeter();
        $routeMatchTelemetry = new RouteMatchTelemetry(
            new NoopTracer(),
            $counterMeter,
            new SteppingTelemetryClock(monotonicStep: 250_000_000),
        );

        $routeMatchTelemetry->observed(
            null,
            self::metadata('GET', '/users'),
            static fn (): MatchResult => MatchResult::notFound(),
        );

        $dimensions = [
            'http.request.method' => 'GET',
            'router.match.outcome' => RouteMatchExplanation::OUTCOME_NOT_FOUND,
        ];
        $duration = $counterMeter->snapshot()[
            MeterSlot::key(RouteMatchTelemetry::MATCH_DURATION_SECONDS, $dimensions)
        ];
        self::assertSame(0.25, $duration['sum'], 'the void span harms nothing; the meter still records');
    }

    /**
     * @return array{RouteMatchTelemetry, InMemorySpanExporter, Tracer, CounterMeter}
     */
    private static function telemetry(): array
    {
        $inMemorySpanExporter = new InMemorySpanExporter();
        $tracer = new Tracer(new BatchSpanProcessor($inMemorySpanExporter), new TelemetryClock());
        $counterMeter = new CounterMeter();

        return [
            new RouteMatchTelemetry($tracer, $counterMeter, new SteppingTelemetryClock()),
            $inMemorySpanExporter,
            $tracer,
            $counterMeter,
        ];
    }

    private static function metadata(string $method, string $path, ?string $host = null): ServerRequestMetadata
    {
        return new ServerRequestMetadata($method, $path, $host, '', '');
    }

    private static function route(string $path, ?string $name = null): CompiledRoute
    {
        $entry = [
            'path' => $path,
            'method' => ['GET'],
            'handler' => 'Handler@handle',
        ];

        if ($name !== null) {
            $entry['name'] = $name;
        }

        return CompiledRoute::fromArray($entry);
    }

    private static function failingMeter(): MeterInterface
    {
        return new class implements MeterInterface {
            #[Override]
            public function increment(string $name, float|int $value = 1, array $attributes = []): void
            {
                throw new LogicException('the meter failed');
            }

            #[Override]
            public function observe(string $name, float $value, array $attributes = []): void
            {
                throw new LogicException('the meter failed');
            }

            #[Override]
            public function snapshot(): array
            {
                throw new LogicException('the meter failed');
            }
        };
    }
}
