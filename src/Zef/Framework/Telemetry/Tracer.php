<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Override;

/**
 * The live tracer: the tracing core's entry point -- it mints the
 * span's identity, wires the processor's handoff into the span it
 * opens, and owns the family's arm.
 *
 * Identity minting follows the W3C trace-context shape: a fresh trace
 * id is 16 random bytes (32 hex characters), a span id is 8 random
 * bytes (16 hex characters), and a span opened under a parent
 * context inherits the parent's trace id -- the parent's own span id
 * stays in the parent, and the child's {@see SpanData} carries the
 * parent context whole. The parent's sampling flag and tracestate
 * companion travel into the child whole: W3C trace-context level 2
 * requires every participant to propagate both unchanged, and the
 * sampling decision the trace-context's flag carries is the
 * propagation lane's question, not the minting's. A root span (no
 * parent context) is minted sampled -- the reference carried no
 * sampler.
 *
 * The arm ({@see self::$enabled}) is the family's fail-closed
 * posture: a disabled tracer answers the void span ({@see
 * NoopSpan}) for every {@see TracerInterface::startSpan()} call --
 * no identity minted, no clock read, no allocation beyond the
 * singleton -- and the caller's code path is identical either way,
 * exactly as the security families' inert middlewares are
 * pass-throughs rather than absent.
 *
 * The drain face ({@see self::flush()}) is the engine-level
 * delegation the export family's cadence reads: the tracer owns
 * the processor, so draining the collection is one call through
 * the face the callers already hold -- the request boundary's
 * flush-per-request knob and the facade the campaign's later
 * family lands both read it here, never the processor directly.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Application/Observability/Tracer.php`): the minting, the parent
 * inheritance and the disabled path move verbatim; the clock moves
 * from static reads into the injected seam the span's whole
 * timeline reads ({@see TelemetryClockInterface}), and the drain
 * face moves from the reference's facade into the tracer the
 * boundary holds.
 */
final readonly class Tracer implements TracerInterface
{
    /**
     * @param BatchSpanProcessor      $batchSpanProcessor the completed-span queue
     *                                                    the spans' records drain
     *                                                    through
     * @param TelemetryClockInterface $telemetryClock     the seam every span's
     *                                                    timeline reads
     * @param bool                    $enabled            the family's arm -- false
     *                                                    answers the void span
     */
    public function __construct(
        private BatchSpanProcessor $batchSpanProcessor,
        private TelemetryClockInterface $telemetryClock,
        private bool $enabled = true,
    ) {
    }

    #[Override]
    public function startSpan(string $name, array $attributes = [], ?SpanContext $spanContext = null): SpanInterface
    {
        if (!$this->enabled) {
            return NoopSpan::instance();
        }

        $traceId = $spanContext->traceId ?? bin2hex(random_bytes(16));
        $spanId = bin2hex(random_bytes(8));
        $context = new SpanContext($traceId, $spanId, $spanContext?->sampled ?? true, $spanContext?->traceState);

        return new Span(
            $name,
            $context,
            $spanContext,
            $this->telemetryClock,
            $this->batchSpanProcessor->onEnd(...),
            $attributes,
        );
    }

    #[Override]
    public function flush(): void
    {
        $this->batchSpanProcessor->flush();
    }
}
