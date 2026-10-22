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
 * wired gets: the spans stay in the process, bounded twice -- by the
 * processor's queue discipline ({@see BatchSpanProcessor}) on the way
 * in, and by the sink's own retention window ({@see self::MAX_SPANS})
 * on the way down -- which is what makes the in-memory default an
 * honest posture under a per-request flush instead of a memory leak:
 * the drain cadence can no longer grow the sink without bound in the
 * persistent workers the kernel targets. The window keeps the most
 * recent spans (a post-mortem reads the tail, not the head) and the
 * suite reads its span records back through the same surface, so the
 * tests observe exactly what the exporter lane would receive, not a
 * parallel test-only channel.
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
    /** The sink's retention window: the most recent spans the observation surface keeps. */
    private const int MAX_SPANS = 4096;

    /** @var list<SpanData> the delivered spans, in delivery order */
    private array $spans = [];

    #[Override]
    public function export(array $spans): void
    {
        foreach ($spans as $span) {
            $this->spans[] = $span;
        }

        // @infection-ignore-all The trim is the identity for every list
        // the sink can hold: spans are appended as a zero-indexed list,
        // so slicing the most recent MAX_SPANS out of a list at or below
        // the bound answers the same values under the same keys the
        // early return keeps -- the guard and its return are pure
        // armour, value-equivalent in both their mutated spellings.
        if (\count($this->spans) <= self::MAX_SPANS) {
            return;
        }

        $this->spans = \array_slice($this->spans, -self::MAX_SPANS);
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
