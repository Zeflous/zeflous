<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The log exporter port: the sink a delivery cadence hands recorded
 * log records to -- the boundary where the recording surface ends
 * and the push lane (the OTLP exporter an armed export family
 * declares) begins, the logs lane beside the span and metric lanes
 * the reference's single exporter always answered.
 *
 * The record is the lane's own value object ({@see LogRecord}):
 * the severity, the body, the timestamp and the attribute map a
 * recording point froze -- the record arrives sanitised or it never
 * arrives at all, because the fold that encodes it never
 * second-guesses a recording's hygiene (the span lane's own
 * doctrine, hygiene a construction, never an encoding).
 *
 * Like the sibling contracts ({@see SpanExporterInterface}, {@see
 * MetricExporterInterface}), the port is deliberately minimal:
 * records in, a shutdown that releases the sink's own state. The
 * cadence that queues the records owns the pacing and the delivery
 * deadlines, never the sink.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Domain/Observability/LogExporterInterface.php`): the verb and
 * the record-typed input move verbatim.
 */
interface LogExporterInterface
{
    /**
     * Delivers one batch of recorded logs.
     *
     * @param list<LogRecord> $records the batch the cadence assembled
     */
    public function exportLogs(array $records): void;

    /**
     * Shuts the sink down: flush or abandon its own buffers, release
     * whatever it held -- the cadence's shutdown calls this exactly
     * once, after its own queue is drained.
     */
    public function shutdown(): void;
}
