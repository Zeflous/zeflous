<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The span contract: the mutable lifecycle a tracer hands its caller,
 * the tracing core's only write surface. A span records attributes,
 * events and a status while it is open, and exactly once it ends --
 * handing its frozen {@see SpanData} record to the end-callback its
 * tracer wired ({@see Span} is the live implementation, {@see
 * NoopSpan} the disabled path).
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Domain/Observability/SpanInterface.php`) over this repository's
 * own naming lineage (the interface-plus-live-implementation pairs
 * the rate-limit family established).
 */
interface SpanInterface
{
    /**
     * The span's identity -- the W3C trace-context value object the
     * propagation lane renders and extracts.
     */
    public function getContext(): SpanContext;

    /**
     * Records one attribute. An ended span ignores the write; an
     * empty key is not a recording.
     *
     * @return $this the span, for a recording chain
     */
    public function setAttribute(string $key, mixed $value): self;

    /**
     * Records every attribute in the map, in order.
     *
     * @param array<string,mixed> $attributes
     *
     * @return $this the span, for a recording chain
     */
    public function setAttributes(array $attributes): self;

    /**
     * Records one timestamped event.
     *
     * @param array<string,mixed> $attributes
     *
     * @return $this the span, for a recording chain
     */
    public function addEvent(string $name, array $attributes = []): self;

    /**
     * Sets the lifecycle status (`UNSET`, `OK`, `ERROR`). An ended
     * span ignores the write; an unknown status is refused.
     *
     * @return $this the span, for a recording chain
     */
    public function setStatus(string $status, ?string $description = null): self;

    /**
     * Ends the span -- idempotently -- handing the frozen record to
     * the tracer's wiring. The explicit end reading overrides the
     * clock's when the caller measured the span itself.
     */
    public function end(?int $endNs = null): void;

    /**
     * Whether the span has ended.
     */
    public function isEnded(): bool;
}
