<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Middleware\TracingFactory;
use Zef\Framework\Telemetry\NoopSpan;
use Zef\Framework\Telemetry\Span;

/**
 * The tracing construction seam, on its own terms: the build from
 * the frozen subtree (an armed tracer whose minted identity answers
 * the W3C grammar over the real system clock), the absent family's
 * fail-closed arm, and the two census halts -- the unknown family
 * and the unknown knob -- through the factory the profile's own
 * service registration names.
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
                . ' (known families: timing, tracing, correlation).',
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
}
