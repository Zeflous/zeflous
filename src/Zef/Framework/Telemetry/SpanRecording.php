<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The span's recording half: the mutable state an open span
 * accumulates -- the attributes, the events, the lifecycle status
 * and its description -- one class holding exactly the writes a
 * span accepts while it is open.
 *
 * The recording knows nothing of the lifecycle (the span's own
 * guard): every write arrives already admitted. It does read the
 * timeline for one thing -- the event timestamp, because the
 * timestamp is the timeline's domain ({@see SpanTimeline::wallNow()}
 * answers it), and an ignored event is never timestamped: the
 * empty-name guard runs before the reading, so a non-recording
 * costs no clock read. The status arrives un-folded -- {@see
 * SpanStatus::fold()} is the one vocabulary the recording reads
 * through.
 *
 * The hygiene lane has landed, exactly where this class reserved
 * the arrival: every recording point reads the family's census
 * ({@see TelemetrySanitizer}) -- a sensitive key is not a
 * recording (the credential never exists in the collection, not
 * even redacted), a recorded value answers the clamp (control
 * bytes stripped, over-budget bytes truncated at a character
 * boundary, NaN and infinities in their string spellings), an
 * event's attributes filter through the same refusal, and the
 * status description clamps to its own budget. The hygiene is
 * structural, not a knob: there is no honest posture in which
 * the collection carries credential material, so no deployment
 * decision exists to make about it.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Application/Observability/Span.php`): the recording behaviour
 * moves verbatim in this class -- the verbatim value storage the
 * reference's evolved successor sanitised at these same points
 * (the `zef-framework-src` Span), the empty-key and empty-name
 * non-recordings, the status fold -- with the guards the span
 * owns lifted out.
 */
final class SpanRecording
{
    private string $status = 'UNSET';

    private ?string $statusDescription = null;

    /** @var array<string,mixed> */
    private array $attributes = [];

    /** @var list<array{name:string,time_unix_nano:int,attributes:array<string,mixed>}> */
    private array $events = [];

    /**
     * @param array<string,mixed> $attributes the attributes the span
     *                                        opens with -- sanitised
     *                                        through the same census
     *                                        every later write reads
     */
    public function __construct(
        private readonly SpanTimeline $spanTimeline,
        array $attributes = [],
    ) {
        $this->setAttributes($attributes);
    }

    /**
     * Records one attribute: an empty key is not a recording, a
     * sensitive key is a refusal -- and the value answers the
     * clamp either way.
     */
    public function setAttribute(string $key, mixed $value): void
    {
        if ($key === '' || TelemetrySanitizer::isSensitiveKey($key)) {
            return;
        }

        $this->attributes[$key] = TelemetryValues::value($value);
    }

    /**
     * Records every attribute in the map, in order.
     *
     * @param array<string,mixed> $attributes
     */
    public function setAttributes(array $attributes): void
    {
        foreach (array_keys($attributes) as $key) {
            $this->setAttribute($key, $attributes[$key]);
        }
    }

    /**
     * Records one timestamped event; an empty name is not a
     * recording -- and never a clock read. The event's attributes
     * filter through the same census the span's own attributes
     * do: a sensitive key never rides an event, and every kept
     * value answers the clamp.
     *
     * @param array<string,mixed> $attributes
     */
    public function addEvent(string $name, array $attributes): void
    {
        if ($name === '') {
            return;
        }

        $this->events[] = [
            'name' => $name,
            'time_unix_nano' => $this->spanTimeline->wallNow(),
            'attributes' => TelemetryValues::attributes($attributes),
        ];
    }

    /**
     * Sets the lifecycle status, folded through the vocabulary,
     * with the description clamped to its own budget.
     */
    public function setStatus(string $status, ?string $description): void
    {
        $this->status = SpanStatus::fold($status);
        $this->statusDescription = $description === null
            ? null
            : TelemetrySanitizer::string($description, 1024);
    }

    /**
     * The lifecycle status, folded.
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * The status' description, when one was set.
     */
    public function statusDescription(): ?string
    {
        return $this->statusDescription;
    }

    /**
     * The recorded attributes.
     *
     * @return array<string,mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * The recorded events, in recording order.
     *
     * @return list<array{name:string,time_unix_nano:int,attributes:array<string,mixed>}>
     */
    public function events(): array
    {
        return $this->events;
    }
}
