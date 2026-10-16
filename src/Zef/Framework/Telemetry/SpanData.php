<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The completed span, frozen: the record a span's lifecycle ends in,
 * the only shape an exporter ever sees -- the tracing core's own
 * discipline that the mutable {@see Span} and the exported record are
 * different types, so nothing downstream can reach back into a span
 * that has already finished and quietly rewrite what was measured.
 *
 * The two time domains stay separate, exactly as the span kept them:
 * `startNs`/`endNs` are monotonic nanoseconds (the duration's own
 * arithmetic, immune to wall-clock adjustment), `startUnixNano`/
 * `endUnixNano` are wall-clock timestamps (the points in real time a
 * timeline aligns spans from different processes by). The span
 * derives the wall end from the monotonic delta -- one wall reading
 * per span, the monotonic seam doing the measuring -- and {@see
 * self::durationSeconds()} is the render of that measurement.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Domain/Observability/SpanData.php`): the record's shape and the
 * duration render move verbatim.
 */
final readonly class SpanData
{
    /**
     * The frozen record's twelve fields, in the order the span handed
     * them over: the name, the minted identity, the parent context it
     * opened under, the monotonic start and end, the wall start and
     * the wall end derived from the monotonic delta, the lifecycle
     * status with its description, and the recorded attributes and
     * events -- the class docblock above tells each field's own
     * story.
     *
     * @param array<string,mixed>                                                        $attributes
     * @param list<array{name:string,time_unix_nano:int,attributes:array<string,mixed>}> $events
     */
    public function __construct(
        public string $name,
        public SpanContext $context,
        public ?SpanContext $parent,
        public int $startNs,
        public int $endNs,
        public int $startUnixNano,
        public int $endUnixNano,
        public string $status,
        public ?string $statusDescription,
        public array $attributes,
        public array $events,
    ) {
    }

    /**
     * The frozen record the span's own halves assemble: the
     * identity it opened with, the timeline's measurement and the
     * recording's accumulated writes, read through their own
     * accessors into the twelve-field record the exporter lane
     * carries -- the one assembly point, so the record's shape and
     * the halves' names cannot drift apart.
     *
     * @internal the span's own assembly point, over the halves only
     *           the span composes
     */
    public static function fromParts(
        string $name,
        SpanContext $context,
        ?SpanContext $parent,
        SpanTimeline $spanTimeline,
        SpanRecording $spanRecording,
    ): self {
        return new self(
            $name,
            $context,
            $parent,
            $spanTimeline->startNs(),
            $spanTimeline->endNs(),
            $spanTimeline->startUnixNano(),
            $spanTimeline->endUnixNano(),
            $spanRecording->status(),
            $spanRecording->statusDescription(),
            $spanRecording->attributes(),
            $spanRecording->events(),
        );
    }

    /**
     * The span's duration, in seconds -- the monotonic delta's render,
     * clamped at zero: a negative delta is a clock artifact, never a
     * negative duration, exactly as the span's own end-time clamp
     * ({@see Span::end()}) holds upstream.
     */
    public function durationSeconds(): float
    {
        return max(0, $this->endNs - $this->startNs) / 1_000_000_000;
    }
}
