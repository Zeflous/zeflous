<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use Closure;
use Override;

/**
 * The live span: one measured operation's mutable lifecycle, from
 * {@see TracerInterface::startSpan()} to {@see self::end()}.
 *
 * The span is the composition of its two halves and the guard
 * between them: the {@see SpanTimeline} owns the measurement (the
 * monotonic start and end, the wall start and the derived wall
 * end), the {@see SpanRecording} owns the accumulated writes (the
 * attributes, the events, the status), and the span owns the
 * lifecycle -- the one `ended` flag that admits every write while
 * the span is open and refuses every one after the single handoff.
 * The reference held all three in one class with three static
 * clock reads scattered across two others; the port composes the
 * halves over the one injected clock ({@see TelemetryClockInterface}),
 * so the suite freezes the entire record with a single double and
 * the mutation run can reach every reading it owns.
 *
 * The record and the lifecycle are different types: while the span
 * is open it accepts attributes, events and a status; the end
 * hands the frozen {@see SpanData} to the tracer's wiring and
 * every later write is ignored -- a span that has finished cannot
 * be rewritten into something it was not. The end is idempotent
 * for the same reason: the record is handed over exactly once,
 * however many times the caller closes a finally block.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Application/Observability/Span.php`), with the campaign's
 * sequencing deviations recorded in the KDD log: the attribute and
 * event values are recorded verbatim -- the telemetry sanitizer the
 * reference filtered sensitive keys through is the campaign's
 * hygiene lane (a later family), and it will arrive at the
 * recording half's own points, not around them.
 */
final class Span implements SpanInterface
{
    private bool $ended = false;

    private readonly SpanTimeline $spanTimeline;

    private readonly SpanRecording $spanRecording;

    /**
     * @param string                  $name           the span's name
     * @param SpanContext             $context        the span's minted identity
     * @param null|SpanContext        $parent         the parent context, when the
     *                                                span joins a trace
     * @param TelemetryClockInterface $telemetryClock the seam the span's whole
     *                                                timeline reads
     * @param Closure(SpanData):void  $onEnd          the tracer's wiring -- the
     *                                                frozen record's one handoff
     * @param array<string,mixed>     $attributes     the attributes the span
     *                                                opens with
     */
    public function __construct(
        private readonly string $name,
        private readonly SpanContext $context,
        private readonly ?SpanContext $parent,
        TelemetryClockInterface $telemetryClock,
        private readonly Closure $onEnd,
        array $attributes = [],
    ) {
        $this->spanTimeline = new SpanTimeline($telemetryClock);
        $this->spanRecording = new SpanRecording($this->spanTimeline, $attributes);
    }

    #[Override]
    public function getContext(): SpanContext
    {
        return $this->context;
    }

    #[Override]
    public function setAttribute(string $key, mixed $value): self
    {
        if (!$this->ended) {
            $this->spanRecording->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Records every attribute in the map, in order.
     *
     * @param array<string,mixed> $attributes
     */
    #[Override]
    public function setAttributes(array $attributes): self
    {
        if (!$this->ended) {
            $this->spanRecording->setAttributes($attributes);
        }

        return $this;
    }

    /**
     * Records one timestamped event.
     *
     * @param array<string,mixed> $attributes
     */
    #[Override]
    public function addEvent(string $name, array $attributes = []): self
    {
        if (!$this->ended) {
            $this->spanRecording->addEvent($name, $attributes);
        }

        return $this;
    }

    #[Override]
    public function setStatus(string $status, ?string $description = null): self
    {
        if (!$this->ended) {
            $this->spanRecording->setStatus($status, $description);
        }

        return $this;
    }

    #[Override]
    public function end(?int $endNs = null): void
    {
        if ($this->ended) {
            return;
        }

        $this->ended = true;
        $this->spanTimeline->close($endNs);

        ($this->onEnd)($this->frozenRecord());
    }

    #[Override]
    public function isEnded(): bool
    {
        return $this->ended;
    }

    /**
     * The frozen record the ended span hands over: the identity it
     * opened with, the timeline's own measurement, and the
     * recording's own accumulated writes -- assembled by the
     * record's own factory ({@see SpanData::fromParts()}).
     */
    private function frozenRecord(): SpanData
    {
        return SpanData::fromParts(
            $this->name,
            $this->context,
            $this->parent,
            $this->spanTimeline,
            $this->spanRecording,
        );
    }
}
