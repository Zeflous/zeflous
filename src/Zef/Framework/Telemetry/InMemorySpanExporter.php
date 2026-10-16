<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Override;

/**
 * The in-process span sink: the exporter that keeps what it is
 * given -- the tracing core's default delivery lane and its own
 * observation surface, in one class.
 *
 * As the default, it is what a family armed without a delivery lane
 * wired gets: the spans stay in the process, bounded by the
 * processor's queue discipline ({@see BatchSpanProcessor}), which is
 * what makes the in-memory default an honest posture rather than a
 * memory leak -- and it is the sink the suite reads its span records
 * back through, so the tests observe exactly what the exporter lane
 * would receive, not a parallel test-only channel.
 *
 * As the observation surface, `spans()` is read-only access and
 * `reset()` clears the record -- a debug console's two operations on
 * the collected telemetry. The shutdown is a no-op because there is
 * nothing to flush: the memory the spans sit in is the sink's whole
 * state.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Application/Observability/InMemorySpanExporter.php`) verbatim in
 * behaviour; the port drops the reference's instanceof introspection
 * helper (`isInMemoryExporter()`) -- it served the telemetry facade
 * this core does not carry yet, and it lands with that facade (a
 * later family in this campaign), not before its consumer.
 */
final class InMemorySpanExporter implements SpanExporterInterface
{
    /** @var list<SpanData> the delivered spans, in delivery order */
    private array $spans = [];

    #[Override]
    public function export(array $spans): void
    {
        foreach ($spans as $span) {
            $this->spans[] = $span;
        }
    }

    #[Override]
    public function shutdown(): void
    {
        // The memory the spans sit in is the sink's whole state:
        // there is nothing to flush and nothing to release.
    }

    /**
     * The spans delivered so far, in delivery order.
     *
     * @return list<SpanData>
     */
    public function spans(): array
    {
        return $this->spans;
    }

    /**
     * Clears the collected record -- the observation surface's reset.
     */
    public function reset(): void
    {
        $this->spans = [];
    }
}
