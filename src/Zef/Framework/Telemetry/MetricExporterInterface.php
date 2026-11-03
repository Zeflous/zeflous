<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The metric exporter port: the sink a delivery cadence hands the
 * meter's accumulated series to -- the boundary where the counting
 * engine ends and the push lane (the OTLP exporter an armed export
 * family declares, the in-process sink a later family may answer
 * with) begins.
 *
 * The port's input is the meter's own answer, verbatim: the exact
 * shape {@see MeterInterface::snapshot()} spells, one entry per
 * series identity with the accumulated count, sum and dimensions.
 * No translation layer sits between the meter and its exporter --
 * the two ends of the seam agree on one shape by construction, so
 * the shape cannot drift apart over them.
 *
 * Like the span lane's own contract ({@see SpanExporterInterface}),
 * the port is deliberately minimal: snapshots in, a shutdown that
 * releases the sink's own state. Pacing, batching and delivery
 * deadlines belong to the cadence that owns the queue, never the
 * sink -- an exporter that answered anything but "delivered" or
 * "threw" would force every other implementation of the seam to
 * carry the same policy state.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Domain/Observability/MetricExporterInterface.php`): the verb
 * and the snapshot-shaped input move verbatim, over this port's
 * already-landed meter contract.
 */
interface MetricExporterInterface
{
    /**
     * Delivers one accumulated series table: the snapshot the meter
     * answered, verbatim -- one entry per series identity with the
     * accumulated count, sum and dimensions.
     *
     * @param array<string,array{count:float|int,sum:float,attributes:array<string,mixed>}> $metrics
     */
    public function exportMetrics(array $metrics): void;

    /**
     * Shuts the sink down: flush or abandon its own buffers, release
     * whatever it held -- the cadence's shutdown calls this exactly
     * once, after its own queue is drained.
     */
    public function shutdown(): void;
}
