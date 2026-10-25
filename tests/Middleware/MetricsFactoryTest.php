<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Health\HealthChecker;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\MetricsFactory;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\MeterSlot;
use Zef\Test\Support\FixedHealthCheck;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The metrics construction seam, on its own terms: the build from
 * the frozen subtree over the two engine services the registration
 * block installs (the shared meter the exposition renders, the
 * health registry the surface polls -- the same instances, not
 * copies), the absent family's fail-closed arm, and the census halt
 * -- through the factory the profile's own service registration
 * names with the campaign's first three-argument construction.
 *
 * @internal
 */
#[CoversClass(MetricsFactory::class)]
final class MetricsFactoryTest extends TestCase
{
    public function testTheFactoryBuildsTheBoundaryFromTheFrozenSubtree(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app.requests', 2);

        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));

        $middleware = MetricsFactory::fromConfig(
            new Config(['observability' => ['metrics' => ['enabled' => true]]]),
            $counterMeter,
            $healthChecker,
        );

        $response = $middleware->process(
            $this->request('GET', '/metrics'),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain; version=0.0.4; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            "# TYPE app_requests counter\napp_requests 2.0\n",
            (string) $response->getBody(),
            'the exposition renders the shared meter the registration wired',
        );
    }

    public function testTheFactoryComposesTheHealthRegistryItWasGiven(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));

        $middleware = MetricsFactory::fromConfig(
            new Config(['observability' => ['metrics' => ['enabled' => true, 'timeout_ms' => 250]]]),
            new CounterMeter(),
            $healthChecker,
        );

        $response = $middleware->process(
            $this->request('GET', '/health'),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('"name":"cache"', (string) $response->getBody());
    }

    public function testTheAbsentFamilyBuildsTheDisarmedBoundary(): void
    {
        $middleware = MetricsFactory::fromConfig(
            new Config([]),
            new CounterMeter(),
            new HealthChecker(),
        );

        $response = $middleware->process(
            $this->request('GET', '/metrics'),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            'answered',
            (string) $response->getBody(),
            'the endpoints do not exist until the deployment arms them',
        );
    }

    public function testTheFactoryAnswersTheCensusHaltTheFamilyOwns(): void
    {
        try {
            MetricsFactory::fromConfig(
                new Config(['observability' => ['metricks' => ['enabled' => true]]]),
                new CounterMeter(),
                new HealthChecker(),
            );
            self::fail('An unknown family must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability configuration carries the unknown family 'metricks'"
                . ' (known families: timing, tracing, correlation, export, metrics, router, slow_request).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFactoryAnswersTheKnobHaltTheFamilyOwns(): void
    {
        try {
            MetricsFactory::fromConfig(
                new Config(['observability' => ['metrics' => ['path' => '/metrics']]]),
                new CounterMeter(),
                new HealthChecker(),
            );
            self::fail('An unknown knob must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.metrics configuration carries the unknown knob 'path'"
                . ' (known knobs: enabled, metrics_path, health_path, timeout_ms).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheMeterConstructorAnswersTheSharedEngine(): void
    {
        $meter = MetricsFactory::meter();
        $meter->increment('app.requests', 2);

        self::assertSame(
            2,
            $meter->snapshot()[MeterSlot::key('app.requests', [])]['count'],
            'the constructor answers a live counter engine, not a disarm',
        );
    }

    /**
     * The request the family's own endpoints are addressed with.
     */
    private function request(string $method, string $path): ServerRequest
    {
        return new ServerRequest($method, new Uri('https://app.example.com' . $path));
    }
}
