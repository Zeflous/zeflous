<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Override;

/**
 * The void tracer: the disabled family's stand-in -- every span
 * request answers the void span, no identity minted, no clock read,
 * no processor wired.
 *
 * It exists because the contract ({@see TracerInterface}) is the
 * seam callers hold: a composition root that arms the family wires
 * the live {@see Tracer}, one that does not wires this, and the
 * caller's code path is identical either way. The live tracer's own
 * disabled arm ({@see Tracer::__construct()}) answers the same void
 * span -- the two postures agree by construction, and the suite
 * pins that agreement.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Application/Observability/NoopTracer.php`) verbatim.
 */
final class NoopTracer implements TracerInterface
{
    #[Override]
    public function startSpan(string $name, array $attributes = [], ?SpanContext $spanContext = null): SpanInterface
    {
        return NoopSpan::instance();
    }
}
