<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Http\TracingPolicy;
use Zef\Framework\Telemetry\BatchSpanProcessor;
use Zef\Framework\Telemetry\InMemorySpanExporter;
use Zef\Framework\Telemetry\TelemetryClock;
use Zef\Framework\Telemetry\Tracer;

/**
 * The tracing family's config-driven construction seam: the factory
 * the framework profile's own `observability.tracing` service
 * registration names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * TracingFactory::class . '::fromConfig'` over the `framework.config`
 * service argument -- resolved through the container's existing
 * declarative grammar, exactly as the four security families' and
 * the timing family's are. This class is the grammar's
 * string-addressed target, which is why it is marked `@api` rather
 * than being baselined or suppressed.
 *
 * Validation is the family's own ({@see TracingPolicy::fromMap()}):
 * every knob read strictly, the unknown family and the unknown knob
 * each a named halt. The construction is the tracing core's engine
 * wiring: the {@see InMemorySpanExporter} sink (the in-process
 * delivery lane -- the OTLP exporter is the campaign's
 * hygiene-and-export family, and this factory is the seam it will
 * widen), the {@see BatchSpanProcessor} over the two bound knobs,
 * the system {@see TelemetryClock}, and the arm -- a disabled family
 * constructs the live tracer already disarmed, so the service is
 * resolvable and answerable at every posture, answering the void
 * span for every call.
 *
 * @api
 */
final readonly class TracingFactory
{
    /**
     * Builds the tracer from the frozen repository's
     * `observability.tracing` subtree.
     */
    public static function fromConfig(Config $config): Tracer
    {
        $tracingPolicy = TracingPolicy::fromMap(
            ObservabilityFamilies::subtree($config->get('observability'), 'tracing'),
        );

        return new Tracer(
            new BatchSpanProcessor(
                new InMemorySpanExporter(),
                $tracingPolicy->maxQueue,
                $tracingPolicy->batchSize,
            ),
            new TelemetryClock(),
            $tracingPolicy->enabled,
        );
    }
}
