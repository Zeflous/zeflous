<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Health\HealthAggregator;
use Zef\Framework\Health\HealthChecker;
use Zef\Framework\Health\HealthGroups;
use Zef\Framework\Http\MetricsPolicy;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\MetricsMiddleware;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\PrometheusRenderer;
use Zef\Test\Support\FixedGroupedHealthCheck;
use Zef\Test\Support\FixedHealthCheck;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\SteppingClock;

/**
 * The exposition boundary, on its own terms: the four endpoints the
 * armed family answers (the scrape, the health base and its two
 * views), the status code the load balancer gates on (200-ok /
 * 503-degraded), the content types and the no-store both surfaces
 * carry, the exact-path GET-only judgement everything else passes
 * through, the custom paths the knobs declare, and the deadline the
 * timeout knob hands the scrape -- the campaign's closing family's
 * face, proven over the two engines it composes.
 *
 * @internal
 */
#[CoversClass(MetricsMiddleware::class)]
#[CoversClass(MetricsPolicy::class)]
final class MetricsMiddlewareTest extends TestCase
{
    public function testTheDisabledFamilyIsAPassThrough(): void
    {
        $response = $this->middleware(enabled: false)
            ->process(
                $this->request('GET', '/metrics'),
                new FixedResponseHandler(new Response(200, [], 'answered')),
            )
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('answered', (string) $response->getBody());
    }

    public function testTheScrapeAnswersTheExpositionOverTheSharedMeter(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app.requests', 2);

        $response = $this->middleware(enabled: true, counterMeter: $counterMeter)
            ->process(
                $this->request('GET', '/metrics'),
                new FixedResponseHandler(new Response(200, [], 'answered')),
            )
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain; version=0.0.4; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(
            "# TYPE app_requests counter\napp_requests 2.0\n",
            (string) $response->getBody(),
        );
    }

    public function testTheScrapeOfAnEmptyMeterAnswersTheEmptyBody(): void
    {
        $response = $this->middleware(enabled: true)
            ->process(
                $this->request('GET', '/metrics'),
                new FixedResponseHandler(new Response(200, [], 'answered')),
            )
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody(), 'no HELP or TYPE line a series did not earn');
    }

    public function testTheHealthBaseAnswersTheWholeRegistry(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));

        $response = $this->middleware(
            enabled: true,
            healthAggregator: $this->frozenAggregator($healthChecker),
        )
            ->process(
                $this->request('GET', '/health'),
                new FixedResponseHandler(new Response(200, [], 'answered')),
            )
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(
            '{"status":"ok","checks":[{"name":"cache","status":"up","message":""}],"tookMs":0}',
            (string) $response->getBody(),
        );
    }

    public function testTheDegradedRegistryAnswersTheLoadBalancers503(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));
        $healthChecker->add(new FixedHealthCheck('database', false));

        $response = $this->middleware(enabled: true, healthChecker: $healthChecker)
            ->process(
                $this->request('GET', '/health'),
                new FixedResponseHandler(new Response(200, [], 'answered')),
            )
        ;

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('"status":"degraded"', (string) $response->getBody());
    }

    public function testTheLiveViewAnswersTheDeclarationAlone(): void
    {
        $healthChecker = new HealthChecker();
        $liveProbe = new FixedGroupedHealthCheck('worker.heartbeat', true, HealthGroups::GROUP_LIVE);
        $readyProbe = new FixedGroupedHealthCheck('database', true, HealthGroups::GROUP_READY);
        $healthChecker->add($liveProbe);
        $healthChecker->add($readyProbe);

        $response = $this->middleware(enabled: true, healthChecker: $healthChecker)
            ->process(
                $this->request('GET', '/health/live'),
                new FixedResponseHandler(new Response(200, [], 'answered')),
            )
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['worker.heartbeat'], $this->checkNames((string) $response->getBody()));
        self::assertSame(0, $readyProbe->probes, 'the ready probe never ran');
    }

    public function testTheReadyViewAnswersEverythingTheLiveDeclarationDoesNotOwn(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedGroupedHealthCheck('worker.heartbeat', true, HealthGroups::GROUP_LIVE));
        $healthChecker->add(new FixedGroupedHealthCheck('database', true, HealthGroups::GROUP_READY));

        $response = $this->middleware(enabled: true, healthChecker: $healthChecker)
            ->process(
                $this->request('GET', '/health/ready'),
                new FixedResponseHandler(new Response(200, [], 'answered')),
            )
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['database'], $this->checkNames((string) $response->getBody()));
    }

    public function testANonGetMethodPassesThrough(): void
    {
        foreach (['POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'] as $method) {
            $response = $this->middleware(enabled: true)
                ->process(
                    $this->request($method, '/metrics'),
                    new FixedResponseHandler(new Response(200, [], 'answered')),
                )
            ;

            self::assertSame(200, $response->getStatusCode(), $method . " is the terminal handler's business");
            self::assertSame('answered', (string) $response->getBody());
        }
    }

    public function testAPathTheFamilyDoesNotOwnPassesThrough(): void
    {
        foreach (['/orders', '/metrics-extra', '/healthcare', '/metrics/'] as $path) {
            $response = $this->middleware(enabled: true)
                ->process(
                    $this->request('GET', $path),
                    new FixedResponseHandler(new Response(200, [], 'answered')),
                )
            ;

            self::assertSame(200, $response->getStatusCode(), $path . " is not the family's surface");
            self::assertSame('answered', (string) $response->getBody());
        }
    }

    public function testTheCustomPathsTheKnobsDeclareAreTheSurfaces(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));

        $middleware = $this->middleware(
            enabled: true,
            metricsPath: '/internal/metrics',
            healthPath: '/status',
            healthAggregator: $this->frozenAggregator($healthChecker),
        );

        $scrape = $middleware->process(
            $this->request('GET', '/internal/metrics'),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );
        $poll = $middleware->process(
            $this->request('GET', '/status/live'),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );

        self::assertSame('text/plain; version=0.0.4; charset=utf-8', $scrape->getHeaderLine('Content-Type'));
        self::assertSame(
            '{"status":"degraded","checks":[{"name":"health.indicators","status":"down",'
            . '"message":"no health indicators registered for group \'live\'"}],"tookMs":0}',
            (string) $poll->getBody(),
            'the empty live view answers its own zero-probe marker',
        );
    }

    public function testTheTimeoutKnobBoundsTheScrapeTheAggregatorRuns(): void
    {
        $healthChecker = new HealthChecker();
        $healthChecker->add(new FixedHealthCheck('cache', true));

        // The readings: 0 at the start, 2_000_000 before the probe -- past
        // the 1ms deadline over 0 -- so the probe is skipped, not run.
        $steppingClock = new SteppingClock(0, 2_000_000);
        $middleware = $this->middleware(
            enabled: true,
            healthChecker: $healthChecker,
            timeoutMs: 1,
            healthAggregator: new HealthAggregator($healthChecker, $steppingClock->now()),
        );

        $response = $middleware->process(
            $this->request('GET', '/health'),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString(
            'skipped: overall health deadline exceeded',
            (string) $response->getBody(),
        );
    }

    /**
     * The check names the JSON body carries.
     *
     * @return list<string>
     */
    private function checkNames(string $json): array
    {
        $decoded = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        $checks = $decoded['checks'] ?? null;
        self::assertIsArray($checks);

        $names = [];
        foreach ($checks as $check) {
            \assert(\is_array($check));
            $name = $check['name'] ?? null;
            \assert(\is_string($name));
            $names[] = $name;
        }

        return $names;
    }

    /**
     * The aggregator over a frozen zero-step clock: the scrape the
     * exact JSON assertions read, its tookMs pinned at zero.
     */
    private function frozenAggregator(HealthChecker $healthChecker): HealthAggregator
    {
        return new HealthAggregator($healthChecker, new SteppingClock(0, 0)->now());
    }

    /**
     * The boundary under test: the family's policy, the shared meter,
     * the health registry and the frozen-clock aggregator the knobs
     * declare.
     */
    private function middleware(
        bool $enabled,
        ?CounterMeter $counterMeter = null,
        ?HealthChecker $healthChecker = null,
        string $metricsPath = '/metrics',
        string $healthPath = '/health',
        int $timeoutMs = 500,
        ?HealthAggregator $healthAggregator = null,
    ): MetricsMiddleware {
        $healthChecker ??= new HealthChecker();

        return new MetricsMiddleware(
            new MetricsPolicy(
                enabled: $enabled,
                metricsPath: $metricsPath,
                healthPath: $healthPath,
                timeoutMs: $timeoutMs,
            ),
            $counterMeter ?? new CounterMeter(),
            new PrometheusRenderer(),
            $healthAggregator ?? new HealthAggregator($healthChecker),
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
