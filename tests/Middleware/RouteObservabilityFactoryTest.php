<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\RouteDebugMiddleware;
use Zef\Framework\Middleware\RouteMatchSpan;
use Zef\Framework\Middleware\RouteMatchTelemetry;
use Zef\Framework\Middleware\RouteObservabilityFactory;
use Zef\Framework\Router\Router;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\MeterSlot;
use Zef\Framework\Telemetry\NoopTracer;
use Zef\Framework\Telemetry\TelemetryClock;
use Zef\Test\Support\Telemetry\SteppingTelemetryClock;

/**
 * The router observability family's construction seam, on its own
 * terms: the telemetry the frozen repository's `observability.
 * router` subtree arms (and the null the disarmed family answers --
 * the honest void, not a noop unit), the debug middleware the
 * `debug_match` disclosure arms (and its own null), and the family's
 * validation travelling through the factory exactly as it travels
 * through the policy.
 *
 * @internal
 */
#[CoversClass(RouteObservabilityFactory::class)]
#[CoversClass(RouteMatchTelemetry::class)]
#[CoversClass(RouteMatchSpan::class)]
#[CoversClass(RouteDebugMiddleware::class)]
final class RouteObservabilityFactoryTest extends TestCase
{
    public function testTheAbsentFamilyAnswersTheNullTelemetry(): void
    {
        self::assertNull(RouteObservabilityFactory::telemetry(
            new Config([]),
            new NoopTracer(),
            new CounterMeter(),
        ));
    }

    public function testTheDisarmedFamilyAnswersTheNullTelemetry(): void
    {
        self::assertNull(RouteObservabilityFactory::telemetry(
            $this->config(['enabled' => false, 'debug_match' => true]),
            new NoopTracer(),
            new CounterMeter(),
        ));
    }

    public function testTheArmedFamilyAnswersTheTelemetryOverTheGivenEngines(): void
    {
        $steppingTelemetryClock = new SteppingTelemetryClock();
        $routeMatchTelemetry = RouteObservabilityFactory::telemetry(
            $this->config(['enabled' => true]),
            new NoopTracer(),
            new CounterMeter(),
            $steppingTelemetryClock,
        );

        self::assertInstanceOf(RouteMatchTelemetry::class, $routeMatchTelemetry);
    }

    public function testTheTelemetryMeasuresOnTheInjectedClock(): void
    {
        $counterMeter = new CounterMeter();
        $routeMatchTelemetry = RouteObservabilityFactory::telemetry(
            $this->config(['enabled' => true]),
            new NoopTracer(),
            $counterMeter,
            new SteppingTelemetryClock(
                monotonicEpoch: 3_000_000_000,
                monotonicStep: 1_000_000_000,
            ),
        );
        self::assertNotNull($routeMatchTelemetry);

        $routeMatchTelemetry->observed(
            new ServerRequest('GET', new Uri('/users')),
            static fn (): MatchResult => MatchResult::notFound(),
        );

        $duration = $counterMeter->snapshot()[MeterSlot::key(RouteMatchTelemetry::MATCH_DURATION_SECONDS, [
            'http.request.method' => 'GET',
            'router.match.outcome' => 'not_found',
        ])];
        self::assertSame(
            1.0,
            $duration['sum'],
            'the injected clock is the clock the observation measures on -- the frozen step, exactly',
        );
    }

    public function testTheAbsentFamilyAnswersTheNullDebugMiddleware(): void
    {
        self::assertNull(RouteObservabilityFactory::debugMiddleware(
            new Config([]),
            $this->router(),
        ));
    }

    public function testTheFamilyWithoutTheDebugArmAnswersTheNullDebugMiddleware(): void
    {
        self::assertNull(RouteObservabilityFactory::debugMiddleware(
            $this->config(['enabled' => true, 'debug_match' => false]),
            $this->router(),
        ));
    }

    public function testTheDebugArmAnswersTheDebugMiddlewareOverTheGivenRouter(): void
    {
        $routeDebugMiddleware = RouteObservabilityFactory::debugMiddleware(
            $this->config(['enabled' => false, 'debug_match' => true]),
            $this->router(),
        );

        self::assertInstanceOf(RouteDebugMiddleware::class, $routeDebugMiddleware);
    }

    public function testTheFactoryTravelsTheFamilyValidation(): void
    {
        try {
            RouteObservabilityFactory::telemetry(
                $this->config(['tracing' => true]),
                new NoopTracer(),
                new CounterMeter(),
                new TelemetryClock(),
            );
            self::fail("An unknown knob must halt the family's construction.");
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.router configuration carries the unknown knob 'tracing'"
                . ' (known knobs: enabled, debug_match).',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @param array<string, bool> $routerSubtree
     */
    private function config(array $routerSubtree): Config
    {
        return new Config(['observability' => ['router' => $routerSubtree]]);
    }

    private function router(): Router
    {
        return Router::fromRoutes([
            ['path' => '/users', 'method' => ['GET'], 'handler' => 'Users@index'],
        ]);
    }
}
