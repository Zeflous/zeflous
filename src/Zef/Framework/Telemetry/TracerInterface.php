<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The tracer contract: the tracing core's entry point -- one call
 * opens a span, under a parent context when there is one, and one
 * call drains the collection the opened spans' records queue
 * into. The live implementation ({@see Tracer}) mints identities
 * and wires the processor; the disabled path ({@see NoopTracer})
 * answers the void span, and the two are interchangeable behind
 * this contract exactly as the security families' armed and inert
 * postures are behind theirs.
 *
 * The drain face ({@see self::flush()}) is the export family's
 * cadence trigger: the request boundary ({@see
 * \Zef\Framework\Middleware\CorrelationMiddleware}) reads it in
 * its finally, after the request span's record has joined the
 * queue, when the deployment's flush-per-request knob is armed --
 * the composition the campaign's fourth family lands. The face
 * belongs to the contract because the boundary holds the tracer,
 * not the processor: the tracer is the engine's face, and
 * draining its own collection is an engine-level operation the
 * later facade the campaign plans will compose the same way.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Domain/Observability/TracerInterface.php`), with the port's
 * widening: the reference's facade owned the flush trigger, and
 * this port's boundary reads it through the contract the callers
 * already hold.
 */
interface TracerInterface
{
    /**
     * Opens one span: a fresh identity under the parent's trace id
     * when a parent context is given, a fresh trace otherwise.
     *
     * @param array<string,mixed> $attributes  the attributes the span
     *                                         opens with
     * @param null|SpanContext    $spanContext the parent context, when
     *                                         the span joins a trace
     */
    public function startSpan(string $name, array $attributes = [], ?SpanContext $spanContext = null): SpanInterface;

    /**
     * Drains the completed-span queue now: every record the ended
     * spans queued is delivered, in batches, with the retry lane's
     * own posture -- the disabled path's collection is always
     * empty, and its drain answers nothing.
     */
    public function flush(): void;
}
