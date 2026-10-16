<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The correlation pair's own propagation boundary: the vendor-neutral
 * extract and inject over the {@see CorrelationContext} -- the
 * operation-level causal context remote and distributed execution
 * correlates through, one level above the {@see
 * TraceContextPropagator}'s span identity.
 *
 * The two levels are deliberately two pairs, and the distinction is
 * the campaign's own: the trace-context pair propagates a SPAN
 * identity (which trace, which parent span -- the tracer's
 * parenting question), while this pair propagates an OPERATION's
 * causal context (which operation, which idempotency key, which
 * bounded diagnostics -- the caller's correlation question). A hop
 * that forwards work answers both, and a hop that only observes
 * answers the first; conflating them is how tracing headers grow
 * application cargo they were never scoped to carry.
 *
 * Inbound propagation is untrusted, and the refusal is null -- the
 * same posture the trace-context pair carries: a malformed or
 * oversized line, a context the constructor would refuse, answers
 * "no correlation present", never an exception across the boundary,
 * because a caller's malformed header must not become the callee's
 * error. The one-attempt null also keeps the disabled path honest:
 * {@see self::disabled()} answers the null the void family runs on
 * -- disabled telemetry creates no context object at all, the
 * reference's own comment, kept as the method that spells it.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Observability/CorrelationPropagator.php`): the
 * tolerant extraction's order (null, oversize, trim, exact width,
 * grammar, constructor-swallow), the injection and the disabled
 * null move verbatim.
 */
final class CorrelationPropagator
{
    /**
     * Extracts the inbound pair into the causal context.
     *
     * @param null|string                               $traceParent    the `traceparent` line as received
     * @param null|string                               $traceState     the `tracestate` companion, when present
     * @param string                                    $operationId    the execution's own operation identity
     * @param null|string                               $idempotencyKey the caller's idempotency identity
     * @param array<string, null|bool|float|int|string> $attributes     the bounded table
     *
     * @return null|CorrelationContext the extracted context, or null when the pair fails the bounds or grammar
     */
    public static function extract(
        ?string $traceParent,
        ?string $traceState,
        string $operationId,
        ?string $idempotencyKey = null,
        array $attributes = [],
    ): ?CorrelationContext {
        if ($traceParent === null) {
            return null;
        }

        if (mb_strlen($traceParent) > CorrelationIdentity::MAX_TRACEPARENT_BYTES) {
            return null;
        }

        if ($traceState !== null) {
            if (mb_strlen($traceState) > CorrelationIdentity::MAX_TRACESTATE_BYTES) {
                return null;
            }
        }

        $value = mb_trim($traceParent);

        if (mb_strlen($value) !== CorrelationIdentity::MAX_TRACEPARENT_BYTES) {
            return null;
        }

        if (preg_match('/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/i', $value, $matches) !== 1) {
            return null;
        }

        try {
            return new CorrelationContext(
                new CorrelationIdentity(
                    mb_strtolower($matches[1]),
                    mb_strtolower($matches[2]),
                    mb_strtolower($matches[3]),
                    $traceState,
                ),
                $operationId,
                $idempotencyKey,
                $attributes,
            );
        } catch (InvalidArgumentException) {
            // The untrusted pair's own refusal: a context the bounds
            // would reject is the null answer, never the boundary's
            // exception.
            return null;
        }
    }

    /**
     * Injects the context into the transport pair -- or null when
     * there is no context to carry, the same null the disabled
     * family's callers read.
     */
    public static function inject(?CorrelationContext $correlationContext): ?CorrelationHeaders
    {
        if (!$correlationContext instanceof CorrelationContext) {
            return null;
        }

        return new CorrelationHeaders(
            $correlationContext->identity->traceParent(),
            $correlationContext->identity->traceState,
        );
    }

    /**
     * The disabled family's answer: deliberately null -- disabled
     * telemetry creates no observability context object.
     */
    public static function disabled(): null
    {
        return null;
    }
}
