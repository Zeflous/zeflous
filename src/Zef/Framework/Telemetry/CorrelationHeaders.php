<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The correlation pair's transport headers: the immutable
 * `traceparent`/`tracestate` couple one hop's wire carries -- the
 * injection's answer, and the shape the next hop's extraction
 * reads.
 *
 * The bounds are the {@see CorrelationContext} constructor's own,
 * re-read at the transport edge: a `traceparent` that is not
 * exactly the fifty-five bytes the W3C render spells, or a
 * `tracestate` that outgrows its ceiling, is refused here too
 * rather than trusted because a context once validated it -- the
 * pair's two ends each enforce the contract, so a header that
 * arrives malformed is a wire artefact, and a header that leaves
 * malformed is a bug this class refuses to ship.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Observability/CorrelationHeaders.php`) verbatim in
 * behaviour: the exact-width parent, the bounded state, the
 * `encodedBytes()` accounting.
 */
final readonly class CorrelationHeaders
{
    /**
     * @param string      $traceParent the W3C `traceparent` line, exactly
     *                                 {@see CorrelationIdentity::MAX_TRACEPARENT_BYTES} bytes
     * @param null|string $traceState  the W3C `tracestate` companion line,
     *                                 when the hop carries one
     *
     * @throws InvalidArgumentException when the parent is not the exact W3C
     *                                  width or the state exceeds its ceiling
     */
    public function __construct(
        public string $traceParent,
        public ?string $traceState,
    ) {
        if (mb_strlen($traceParent) !== CorrelationIdentity::MAX_TRACEPARENT_BYTES) {
            throw new InvalidArgumentException('Correlation traceparent must be 55 bytes.');
        }

        if ($traceState !== null && mb_strlen($traceState) > CorrelationIdentity::MAX_TRACESTATE_BYTES) {
            throw new InvalidArgumentException('Correlation tracestate exceeds the hard limit.');
        }
    }

    /**
     * The pair's wire accounting: the bytes the two header lines
     * carry, without the separator the context's own {@see
     * CorrelationContext::propagationBytes()} bookkeeping adds --
     * the headers' own width, answered for the record.
     */
    public function encodedBytes(): int
    {
        return mb_strlen($this->traceParent) + ($this->traceState === null ? 0 : mb_strlen($this->traceState));
    }
}
