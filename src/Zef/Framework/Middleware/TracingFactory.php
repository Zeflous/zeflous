<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\ExportPolicy;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Http\TracingPolicy;
use Zef\Framework\Telemetry\BatchSpanProcessor;
use Zef\Framework\Telemetry\InMemorySpanExporter;
use Zef\Framework\Telemetry\OtlpHttpJsonExporter;
use Zef\Framework\Telemetry\RetryBackoffPolicy;
use Zef\Framework\Telemetry\SpanExporterInterface;
use Zef\Framework\Telemetry\TelemetryClock;
use Zef\Framework\Telemetry\Tracer;

/**
 * The tracing family's config-driven construction seam: the factory
 * the framework profile's own `observability.tracing` service
 * registration names -- widened, exactly as its own record reserved,
 * into the export family's composition point.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * TracingFactory::class . '::fromConfig'` over the `framework.config`
 * service argument -- resolved through the container's existing
 * declarative grammar, exactly as the four security families', the
 * timing family's and the correlation family's are. This class is
 * the grammar's string-addressed target, which is why it is marked
 * `@api` rather than being baselined or suppressed.
 *
 * Validation is each family's own ({@see TracingPolicy::fromMap()},
 * {@see ExportPolicy::fromMap()}): every knob read strictly, the
 * unknown family and the unknown knob each a named halt. The
 * construction is the two families' engine wiring: the sink -- the
 * in-memory exporter by default, swapped for the {@see
 * OtlpHttpJsonExporter} an armed export family declares, over the
 * resource block the policy's service identity heads -- the {@see
 * BatchSpanProcessor} over the two bound knobs and the retry lane
 * the export policy shapes ({@see RetryBackoffPolicy}), the system
 * {@see TelemetryClock}, and the arm -- a disabled family constructs
 * the live tracer already disarmed, so the service is resolvable
 * and answerable at every posture, answering the void span for
 * every call.
 *
 * The export family arms through this seam and no service of its
 * own: the lane IS the tracing family's sink and retry posture,
 * and the drain cadence the correlation boundary carries ({@see
 * CorrelationFactory}) -- the one registration the tracing family
 * already owns is the honest wiring point, and a deployment that
 * arms export writes knobs the one factory reads.
 *
 * @api
 */
final readonly class TracingFactory
{
    /**
     * Builds the tracer from the frozen repository's
     * `observability.tracing` subtree, over the export family's own
     * subtree for the delivery lane the tracer's processor carries.
     */
    public static function fromConfig(Config $config): Tracer
    {
        $tracingPolicy = TracingPolicy::fromMap(
            ObservabilityFamilies::subtree($config->get('observability'), 'tracing'),
        );
        $exportPolicy = ExportPolicy::fromMap(
            ObservabilityFamilies::subtree($config->get('observability'), 'export'),
        );

        return new Tracer(
            new BatchSpanProcessor(
                self::spanExporter($exportPolicy),
                $tracingPolicy->maxQueue,
                $tracingPolicy->batchSize,
                new RetryBackoffPolicy(
                    $exportPolicy->maxRetries,
                    $exportPolicy->retryDelayMs,
                    $exportPolicy->retryDelayCapMs,
                ),
            ),
            new TelemetryClock(),
            $tracingPolicy->enabled,
        );
    }

    /**
     * Answers the delivery lane's sink: the OTLP exporter an armed
     * family declares -- over the endpoint, the resource block the
     * service identity heads and the exchange's wait -- or the
     * in-memory exporter the disarmed posture keeps, the honest
     * default a family without a collector declaration gets ({@see
     * InMemorySpanExporter}'s own record).
     */
    private static function spanExporter(ExportPolicy $exportPolicy): SpanExporterInterface
    {
        if (!$exportPolicy->enabled) {
            return new InMemorySpanExporter();
        }

        return new OtlpHttpJsonExporter(
            $exportPolicy->endpoint,
            [
                'service.name' => $exportPolicy->serviceName,
                'telemetry.sdk.name' => 'zef-observability',
                'telemetry.sdk.language' => 'php',
            ],
            $exportPolicy->timeoutMs,
        );
    }
}
