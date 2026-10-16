<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Throwable;

/**
 * The span exporter port: the sink a processor hands completed-span
 * records to -- the boundary where the tracing core ends and the
 * delivery lane ({@see InMemorySpanExporter} the in-process sink, the
 * OTLP lane a later family in this campaign owns) begins.
 *
 * The port is deliberately minimal: batches in, a shutdown that
 * flushes the sink's own state. Retry, pacing and delivery deadlines
 * are the processor's concerns, never the sink's -- an exporter that
 * answered anything but "delivered" or "threw" would force every
 * other implementation of the seam to carry the same policy state.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Domain/Observability/SpanExporterInterface.php`).
 */
interface SpanExporterInterface
{
    /**
     * Delivers one batch of completed spans.
     *
     * @param list<SpanData> $spans the batch the processor assembled
     *
     * @throws Throwable when the delivery failed -- the processor
     *                   owns what happens next
     */
    public function export(array $spans): void;

    /**
     * Shuts the sink down: flush or abandon its own buffers, release
     * whatever it held -- the processor's shutdown calls this exactly
     * once, after its own queue is drained.
     */
    public function shutdown(): void;
}
