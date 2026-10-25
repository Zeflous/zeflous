<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\CorrelationFactory;
use Zef\Framework\Telemetry\BatchSpanProcessor;
use Zef\Framework\Telemetry\InMemorySpanExporter;
use Zef\Framework\Telemetry\TelemetryClock;
use Zef\Framework\Telemetry\Tracer;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The correlation construction seam, on its own terms: the build
 * from the frozen subtree over the tracer the registration wired
 * (the same instance the tracing family resolves, not a copy -- the
 * request spans drain through the one processor the deployment
 * configured), the absent family's fail-closed arm, and the census
 * halts -- the unknown family and the unknown knob -- through the
 * factory the profile's own service registration names with the
 * campaign's first second service argument.
 *
 * @internal
 */
#[CoversClass(CorrelationFactory::class)]
final class CorrelationFactoryTest extends TestCase
{
    private InMemorySpanExporter $inMemorySpanExporter;

    private BatchSpanProcessor $batchSpanProcessor;

    #[Override]
    protected function setUp(): void
    {
        $this->inMemorySpanExporter = new InMemorySpanExporter();
        $this->batchSpanProcessor = new BatchSpanProcessor($this->inMemorySpanExporter);
    }

    public function testTheFactoryBuildsTheBoundaryFromTheFrozenSubtree(): void
    {
        $middleware = CorrelationFactory::fromConfig(
            new Config(['observability' => ['correlation' => ['enabled' => true]]]),
            $this->tracer(true),
        );

        $response = $middleware->process(
            $this->request(),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('answered', (string) $response->getBody());
        self::assertSame('', $response->getHeaderLine('traceparent'), 'the disarmed disclosure stays header-silent');
    }

    public function testTheFactoryComposesTheTracerItWasGiven(): void
    {
        $tracer = $this->tracer(true);

        $middleware = CorrelationFactory::fromConfig(
            new Config(['observability' => ['correlation' => ['enabled' => true]]]),
            $tracer,
        );

        $middleware->process($this->request(), new FixedResponseHandler(new Response(200)));

        $this->batchSpanProcessor->flush();

        $names = [];

        foreach ($this->inMemorySpanExporter->spans() as $spanData) {
            $names[] = $spanData->name;
        }

        self::assertContains('zef.http.request', $names, "the boundary's spans drain through the given tracer");
    }

    public function testTheFactoryArmsTheDrainCadenceFromTheExportSubtree(): void
    {
        // The drain cadence is the export family's knob, read on the
        // correlation boundary's own construction seam: an armed
        // flush_per_request answers the request with the queue
        // already drained -- no later flush, the middleware's own
        // finally did the work.
        $middleware = CorrelationFactory::fromConfig(
            new Config([
                'observability' => [
                    'correlation' => ['enabled' => true],
                    'export' => ['flush_per_request' => true],
                ],
            ]),
            $this->tracer(true),
        );

        $middleware->process($this->request(), new FixedResponseHandler(new Response(200)));

        self::assertCount(
            1,
            $this->inMemorySpanExporter->spans(),
            'the request boundary itself drained the span -- the cadence the export family armed',
        );
    }

    public function testTheFactoryLeavesTheAbsentCadenceToTheDeployment(): void
    {
        $middleware = CorrelationFactory::fromConfig(
            new Config([
                'observability' => [
                    'correlation' => ['enabled' => true],
                    'export' => ['flush_per_request' => false],
                ],
            ]),
            $this->tracer(true),
        );

        $middleware->process($this->request(), new FixedResponseHandler(new Response(200)));

        self::assertSame(
            [],
            $this->inMemorySpanExporter->spans(),
            'the absent cadence ships the drain OFF -- the queue holds the span for the deployment own drain',
        );
    }

    public function testTheFactoryBuildsTheFailClosedArmFromTheAbsentFamily(): void
    {
        $middleware = CorrelationFactory::fromConfig(new Config([]), $this->tracer(false));

        $response = $middleware->process(
            $this->request(),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );

        self::assertSame('answered', (string) $response->getBody());
        self::assertSame('', $response->getHeaderLine('traceparent'));
    }

    public function testTheFactoryHaltsOnAnUnknownFamily(): void
    {
        $config = new Config([
            'observability' => [
                'correlaton' => [],
            ],
        ]);

        try {
            CorrelationFactory::fromConfig($config, $this->tracer(false));
            self::fail('An unknown observability family must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability configuration carries the unknown family 'correlaton'"
                . ' (known families: timing, tracing, correlation, export, metrics, router, slow_request).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFactoryHaltsOnANonMapObservabilityRoot(): void
    {
        $config = new Config([
            'observability' => 'on',
        ]);

        try {
            CorrelationFactory::fromConfig($config, $this->tracer(false));
            self::fail('A non-map observability root must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFactoryHaltsOnAnUnknownKnob(): void
    {
        $config = new Config([
            'observability' => [
                'correlation' => [
                    'echo' => true,
                ],
            ],
        ]);

        try {
            CorrelationFactory::fromConfig($config, $this->tracer(false));
            self::fail('An unknown knob must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.correlation configuration carries the unknown knob 'echo'"
                . ' (known knobs: enabled, echo_traceparent).',
                $httpException->getMessage(),
            );
        }
    }

    private function tracer(bool $enabled): Tracer
    {
        return new Tracer(
            $this->batchSpanProcessor,
            new TelemetryClock(),
            $enabled,
        );
    }

    private function request(): ServerRequest
    {
        return new ServerRequest('GET', new Uri('https://app.example.com/'));
    }
}
