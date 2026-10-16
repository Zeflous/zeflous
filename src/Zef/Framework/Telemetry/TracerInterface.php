<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The tracer contract: the tracing core's entry point -- one call
 * opens a span, under a parent context when there is one. The live
 * implementation ({@see Tracer}) mints identities and wires the
 * processor; the disabled path ({@see NoopTracer}) answers the void
 * span, and the two are interchangeable behind this contract exactly
 * as the security families' armed and inert postures are behind
 * theirs.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Domain/Observability/TracerInterface.php`).
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
}
