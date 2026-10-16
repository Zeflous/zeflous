<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The W3C trace-context identity one span carries: the trace id, the
 * span id, the sampling flag and the (optional) trace-state the
 * propagation lane will read -- the value object the tracing core's
 * whole vocabulary addresses a span by.
 *
 * The identifiers are validated at construction, not on use: the W3C
 * trace-context grammar is a 32-hex-character trace id and a
 * 16-hex-character span id, and anything else is refused the moment
 * it is offered rather than the moment it is rendered -- a malformed
 * identity that reached a `traceparent` render would poison every
 * downstream participant's context, and the earliest refusal is the
 * cheapest one. The all-zero pair is grammatically well-formed but
 * semantically void ({@see self::invalid()} is its only legitimate
 * producer), so validity is a separate question the render never
 * conflates with grammarity.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Domain/Observability/SpanContext.php`): the grammar, the
 * `traceparent` render and the invalid singleton move verbatim; the
 * trace-state field rides along unvalidated -- the propagation lane
 * that owns its `key=value;key=value` grammar is the one that will
 * parse it (a later family in this campaign), and validating it here
 * would duplicate that lane's own contract.
 */
final readonly class SpanContext
{
    /**
     * @param string      $traceId    the trace's identity, 32 lowercase
     *                                hex characters
     * @param string      $spanId     the span's identity within the
     *                                trace, 16 lowercase hex characters
     * @param bool        $sampled    the W3C sampled flag the
     *                                `traceparent` render spells as the
     *                                trailing `01`/`00`
     * @param null|string $traceState the W3C `tracestate` companion
     *                                value, when a propagation lane
     *                                carried one
     *
     * @throws InvalidArgumentException when either identifier fails
     *                                  the W3C trace-context grammar
     */
    public function __construct(
        public string $traceId,
        public string $spanId,
        public bool $sampled = true,
        public ?string $traceState = null,
    ) {
        if (
            preg_match('/^[0-9a-f]{32}$/', $traceId) !== 1
            || preg_match('/^[0-9a-f]{16}$/', $spanId) !== 1
        ) {
            throw new InvalidArgumentException('Invalid W3C trace context identifiers.');
        }
    }

    /**
     * The W3C `traceparent` header value this context renders as --
     * the exact `00-<trace>-<span>-<flag>` shape the trace-context
     * propagation lane both extracts and injects.
     */
    public function traceParent(): string
    {
        return '00-' . $this->traceId . '-' . $this->spanId . '-' . ($this->sampled ? '01' : '00');
    }

    /**
     * The void context: the all-zero identity, unsampled -- the only
     * answer a span outside a trace can carry ({@see NoopSpan} reads
     * it), and the value the propagation lane treats as "no context
     * present".
     */
    public static function invalid(): self
    {
        return new self(str_repeat('0', 32), str_repeat('0', 16), false);
    }

    /**
     * Whether this context identifies a span at all -- the all-zero
     * pair is grammatically valid and semantically void, and every
     * consumer that would propagate it must refuse it here first.
     */
    public function isValid(): bool
    {
        return $this->traceId !== str_repeat('0', 32) && $this->spanId !== str_repeat('0', 16);
    }
}
