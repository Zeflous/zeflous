<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The correlation context's W3C identity: the trace id, the span id,
 * the flags and the `tracestate` companion -- the four fields the
 * propagation wire carries, validated as one unit at construction,
 * with the render and the wire accounting they own.
 *
 * The identity is the context's own first half, extracted as its own
 * value object for the same reason the tracing core extracted its
 * halves: the W3C grammar and the wire bounds are one unit's
 * contract, and the causal context around it ({@see
 * CorrelationContext}) composes it rather than re-validating it.
 * Everything downstream -- the {@see CorrelationHeaders} transport
 * pair, the operation-level {@see CorrelationPropagator} -- receives
 * an identity that already satisfies every bound the wire enforces.
 *
 * The wire-size guard lives here because the wire it guards is this
 * unit's own: the `traceparent` render plus the `tracestate`
 * companion is the whole propagation surface the identity can grow,
 * and the hard ceiling on it is the last bound the constructor
 * reads.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Observability/CorrelationContext.php`): the four
 * identity validators, the `tracestate` member grammar, the
 * `traceparent` render, the separator-inclusive accounting and the
 * hard wire ceiling move verbatim, in the identity's own class --
 * with the port's one deviation, the dropped empty-tracestate
 * guard, which the port's own mutation campaign proved
 * unreachable-in-effect: the member grammar refuses the empty
 * companion with the same refusal.
 */
final readonly class CorrelationIdentity
{
    /** The propagation pair's aggregate wire ceiling. */
    public const int MAX_PROPAGATION_BYTES = 8192;

    /** The `traceparent` render's exact width. */
    public const int MAX_TRACEPARENT_BYTES = 55;

    /** The `tracestate` companion's ceiling. */
    public const int MAX_TRACESTATE_BYTES = 512;

    /**
     * The W3C `tracestate` grammar: a list of `key=value` members,
     * the keys lowercase vendor-prefixed identifiers, the values
     * printable ASCII without the separators, the members joined by
     * an optional space after the comma.
     */
    private const string TRACESTATE_GRAMMAR
        = '/^[a-z0-9][_a-z0-9\-*\/]{0,255}=[!#$%&\x27*+\-.\/0-9:<=>?@A-Z\^_`a-z|~]*'
        . '(?:, ?[a-z0-9][_a-z0-9\-*\/]{0,255}=[!#$%&\x27*+\-.\/0-9:<=>?@A-Z\^_`a-z|~]*)*$/';

    /**
     * @param string      $traceId    32 lowercase hex characters
     * @param string      $spanId     16 lowercase hex characters
     * @param string      $traceFlags the two-hex-digit flags, the sampled bit alone
     * @param null|string $traceState the W3C `tracestate` companion line
     *
     * @throws InvalidArgumentException when a field fails the W3C grammar,
     *                                  or the pair exceeds the hard wire ceiling
     */
    public function __construct(
        public string $traceId,
        public string $spanId,
        public string $traceFlags,
        public ?string $traceState,
    ) {
        $this->validateTraceId($traceId);
        $this->validateSpanId($spanId);
        $this->validateTraceFlags($traceFlags);
        $this->validateTraceState($traceState);

        if ($this->propagationBytes() > self::MAX_PROPAGATION_BYTES) {
            throw new InvalidArgumentException('Correlation propagation exceeds the hard size limit.');
        }
    }

    /**
     * The W3C `traceparent` render this identity spells -- the exact
     * `00-<trace>-<span>-<flags>` shape the propagation pair both
     * extracts and injects.
     */
    public function traceParent(): string
    {
        return '00-' . $this->traceId . '-' . $this->spanId . '-' . $this->traceFlags;
    }

    /**
     * The propagation pair's wire accounting: the `traceparent`
     * render plus the `tracestate` companion and its separator --
     * the same arithmetic the constructor's ceiling read, answered
     * for the record.
     */
    public function propagationBytes(): int
    {
        return $this->traceParentLength()
            + ($this->traceState === null ? 0 : mb_strlen($this->traceState, '8bit') + 1);
    }

    /**
     * @throws InvalidArgumentException when the trace id fails the W3C grammar or is the all-zero identity
     */
    private function validateTraceId(string $value): void
    {
        if (preg_match('/^[0-9a-f]{32}$/', $value) !== 1 || $value === str_repeat('0', 32)) {
            throw new InvalidArgumentException('Invalid W3C trace ID.');
        }
    }

    /**
     * @throws InvalidArgumentException when the span id fails the W3C grammar or is the all-zero identity
     */
    private function validateSpanId(string $value): void
    {
        if (preg_match('/^[0-9a-f]{16}$/', $value) !== 1 || $value === str_repeat('0', 16)) {
            throw new InvalidArgumentException('Invalid W3C span ID.');
        }
    }

    /**
     * @throws InvalidArgumentException when the flags are not two hex digits carrying the sampled bit alone
     */
    private function validateTraceFlags(string $value): void
    {
        if (preg_match('/^[0-9a-f]{2}$/', $value) !== 1 || ((hexdec($value) & 0xFE) !== 0)) {
            throw new InvalidArgumentException('Invalid W3C trace flags.');
        }
    }

    /**
     * @throws InvalidArgumentException when the trace-state is empty, oversized or fails the member grammar
     */
    private function validateTraceState(?string $value): void
    {
        if ($value === null) {
            return;
        }

        if (mb_strlen($value, '8bit') > self::MAX_TRACESTATE_BYTES) {
            throw new InvalidArgumentException('Invalid or oversized W3C tracestate.');
        }

        if (preg_match(self::TRACESTATE_GRAMMAR, $value) !== 1) {
            throw new InvalidArgumentException('Invalid or oversized W3C tracestate.');
        }
    }

    /**
     * The `traceparent` render's width for the identities it spells
     * -- the version, the three separators and the three fields.
     */
    private function traceParentLength(): int
    {
        return 2 + 1 + mb_strlen($this->traceId, '8bit') + 1 + mb_strlen($this->spanId, '8bit') + 1
            + mb_strlen($this->traceFlags, '8bit');
    }
}
