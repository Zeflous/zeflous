<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Middleware\TracingFactory;
use Zef\Framework\Telemetry\NoopSpan;
use Zef\Framework\Telemetry\Span;
use Zef\Test\Support\Telemetry\OtlpMockServer;

/**
 * The tracing construction seam, on its own terms: the build from
 * the frozen subtree (an armed tracer whose minted identity answers
 * the W3C grammar over the real system clock), the absent family's
 * fail-closed arm, and the two census halts -- the unknown family
 * and the unknown knob -- through the factory the profile's own
 * service registration names.
 *
 * The export family widened the same seam, and the widening is
 * pinned the real-path way: an armed export family swaps the
 * delivery lane's sink for the OTLP exporter a real loopback
 * collector answers ({@see OtlpMockServer}), a disarmed family
 * delivers nothing over the wire, and the retry lane reads the
 * family's own knobs -- the collector's leading 500 answered by
 * the declared backoff's second attempt.
 *
 * @internal
 */
#[CoversClass(TracingFactory::class)]
final class TracingFactoryTest extends TestCase
{
    public function testTheFactoryBuildsTheEngineFromTheFrozenSubtree(): void
    {
        $config = new Config([
            'observability' => [
                'tracing' => [
                    'enabled' => true,
                ],
            ],
        ]);

        $span = TracingFactory::fromConfig($config)->startSpan('request');

        self::assertInstanceOf(Span::class, $span);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $span->getContext()->traceId);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/D', $span->getContext()->spanId);
        self::assertTrue($span->getContext()->sampled);
    }

    public function testAnArmedExportFamilySwapsTheSinkTheRealCollectorAnswers(): void
    {
        $otlpMockServer = new OtlpMockServer();
        $endpoint = $otlpMockServer->start();

        $config = new Config([
            'observability' => [
                'tracing' => [
                    'enabled' => true,
                ],
                'export' => [
                    'enabled' => true,
                    'endpoint' => $endpoint,
                    'service_name' => 'factory-test',
                ],
            ],
        ]);

        $tracer = TracingFactory::fromConfig($config, self::noRegistrar());
        $tracer->startSpan('request')->end();
        $tracer->flush();

        $bodies = $otlpMockServer->receivedBodies();
        self::assertCount(1, $bodies, 'the armed family delivers the drained batch to the collector');

        $payload = json_decode($bodies[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $resourceSpans = $payload['resourceSpans'];
        self::assertIsArray($resourceSpans);
        $resourceSpan = $resourceSpans[0];
        self::assertIsArray($resourceSpan);
        $resource = $resourceSpan['resource'];
        self::assertIsArray($resource);
        $attributes = $resource['attributes'];
        self::assertIsArray($attributes);
        $serviceIdentity = $attributes[0];
        self::assertIsArray($serviceIdentity);
        $value = $serviceIdentity['value'];
        self::assertIsArray($value);

        self::assertSame(
            'factory-test',
            $value['stringValue'],
            'the service identity the knob declared rides the resource block',
        );

        $otlpMockServer->stop();
    }

    public function testADisarmedExportFamilyNeverOpensTheWireLane(): void
    {
        // The collector is real and listening: a disarmed family
        // that still built the OTLP exporter would deliver the
        // drained batch to it. The in-memory sink is the honest
        // default a family without a collector declaration gets,
        // and nothing crosses the wire.
        $otlpMockServer = new OtlpMockServer();
        $endpoint = $otlpMockServer->start();

        $config = new Config([
            'observability' => [
                'tracing' => [
                    'enabled' => true,
                ],
                'export' => [
                    'enabled' => false,
                    'endpoint' => $endpoint,
                ],
            ],
        ]);

        $tracer = TracingFactory::fromConfig($config, self::noRegistrar());
        $tracer->startSpan('request')->end();
        $tracer->flush();

        self::assertSame(
            0,
            $otlpMockServer->requests(),
            'the disarmed family delivers nothing -- the drain stays in-process',
        );

        $otlpMockServer->stop();
    }

    public function testTheRetryLaneReadsTheFamilysOwnKnobs(): void
    {
        // The collector's leading answer is the transient 500 the
        // lane's own exception carries, and the declared backoff
        // earns the batch its second attempt -- one millisecond, so
        // the suite never waits the deployment-grade delays.
        $otlpMockServer = new OtlpMockServer(failFirst: 1);
        $endpoint = $otlpMockServer->start();

        $config = new Config([
            'observability' => [
                'tracing' => [
                    'enabled' => true,
                ],
                'export' => [
                    'enabled' => true,
                    'endpoint' => $endpoint,
                    'max_retries' => 2,
                    'retry_delay_ms' => 1,
                ],
            ],
        ]);

        $tracer = TracingFactory::fromConfig($config, self::noRegistrar());
        $tracer->startSpan('request')->end();
        $tracer->flush();

        self::assertSame(
            2,
            $otlpMockServer->requests(),
            'the leading 500 earned its retry, and the second attempt delivered',
        );

        $otlpMockServer->stop();
    }

    public function testAnArmedFamilyWiresTheExitDrainTheShutdownObserverDelivers(): void
    {
        // The audit's dead-lane shape: a deployment that arms the
        // family but leaves the per-request cadence off still
        // delivers -- the registered exit drain flushes the queue
        // the process leaves behind.
        $otlpMockServer = new OtlpMockServer();
        $endpoint = $otlpMockServer->start();

        $observers = [];
        $config = new Config([
            'observability' => [
                'tracing' => ['enabled' => true],
                'export' => [
                    'enabled' => true,
                    'endpoint' => $endpoint,
                    'service_name' => 'factory-test',
                ],
            ],
        ]);

        $tracer = TracingFactory::fromConfig(
            $config,
            static function (callable $observer) use (&$observers): void {
                $observers[] = $observer;
            },
        );

        $tracer->startSpan('request')->end();

        self::assertCount(1, $observers, 'the armed family registered its exit drain');
        self::assertSame(0, $otlpMockServer->requests(), 'nothing crossed the wire before the exit');

        ($observers[0])();

        self::assertCount(1, $otlpMockServer->receivedBodies(), 'the exit drain delivered the queued batch');

        $otlpMockServer->stop();
    }

    public function testADisarmedFamilyWiresNoExitDrain(): void
    {
        $observers = [];
        $config = new Config([
            'observability' => [
                'tracing' => ['enabled' => true],
                'export' => ['enabled' => false],
            ],
        ]);

        TracingFactory::fromConfig(
            $config,
            static function (callable $observer) use (&$observers): void {
                $observers[] = $observer;
            },
        );

        self::assertSame([], $observers, 'the in-memory default keeps the drain in-process');
    }

    public function testTheFactoryBuildsTheFailClosedArmFromTheAbsentFamily(): void
    {
        $tracer = TracingFactory::fromConfig(new Config([]));

        self::assertInstanceOf(NoopSpan::class, $tracer->startSpan('request'));
    }

    public function testTheFactoryBuildsADisarmedTracer(): void
    {
        $config = new Config([
            'observability' => [
                'tracing' => [
                    'enabled' => false,
                ],
            ],
        ]);

        self::assertInstanceOf(NoopSpan::class, TracingFactory::fromConfig($config)->startSpan('request'));
    }

    public function testTheFactoryHaltsOnAnUnknownFamily(): void
    {
        $config = new Config([
            'observability' => [
                'trcing' => [],
            ],
        ]);

        try {
            TracingFactory::fromConfig($config);
            self::fail('An unknown observability family must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability configuration carries the unknown family 'trcing'"
                . ' (known families: timing, tracing, correlation, export, metrics).',
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
            TracingFactory::fromConfig($config);
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
                'tracing' => [
                    'queue' => 512,
                ],
            ],
        ]);

        try {
            TracingFactory::fromConfig($config);
            self::fail('An unknown knob must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.tracing configuration carries the unknown knob 'queue'"
                . ' (known knobs: enabled, max_queue, batch_size).',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * The registrar double that keeps the suite's engine stacks clean:
     * no real shutdown observer lands at the PHPUnit exit.
     */
    /**
     * @return Closure(callable(): void): void
     */
    private static function noRegistrar(): Closure
    {
        return static function (): void {
        };
    }
}
