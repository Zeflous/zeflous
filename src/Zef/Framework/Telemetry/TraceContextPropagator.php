<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The W3C trace-context propagation pair over the span identity: the
 * inbound `traceparent`/`tracestate` header pair extracted into the
 * {@see SpanContext} the tracer parents a span with, and the outbound
 * render back -- the correlation campaign's own seam between the
 * tracing core's identities and the wire.
 *
 * Inbound propagation is untrusted: the header line arrives from the
 * client, and the extract answers null for anything the W3C grammar
 * does not spell -- a wrong version, a short trace id, a non-hex
 * span id, a flag byte outside the sampled bit, the all-zero pair,
 * an over-long line. Null is the refusal, never an exception: the
 * caller treats "no parent present" and "parent present but
 * malformed" as the same honest answer (a fresh trace is minted
 * either way), because a malformed header is a client's mistake or
 * a probe, and neither deserves a 500.
 *
 * The pair's two halves are deliberately asymmetric in shape: the
 * extract normalises (trim, lowercase -- the W3C grammar is
 * case-insensitive in the field's spec, and the {@see SpanContext}
 * constructor's lowercase grammar is the one the record keeps), the
 * inject is the context's own {@see SpanContext::traceParent()}
 * render spelled as one call so the propagation lane reads as a
 * pair -- the same one-method shape the reference's own propagator
 * carried.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Observability/TraceContextPropagator.php`): the
 * grammar, the all-zero refusal, the flag mask and the lowercase
 * normalisation move verbatim; the trace-state rides along
 * unvalidated -- its `key=value` grammar is the correlation
 * context's own contract ({@see CorrelationContext}), which
 * validates it at construction, and the span identity this pair
 * extracts only needs to carry it.
 */
final class TraceContextPropagator
{
    /** The inbound line's W3C grammar, the fields the extract reads. */
    private const string TRACE_CONTEXT_GRAMMAR
        = '/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/i';

    /**
     * Extracts the inbound trace-context pair into the parent span
     * context.
     *
     * @param string      $traceParent the `traceparent` header line as
     *                                 the request carries it (the empty
     *                                 string when absent -- the grammar
     *                                 refuses it, which is the absent
     *                                 answer)
     * @param null|string $traceState  the `tracestate` companion line,
     *                                 when the request carried one
     *
     * @return null|SpanContext the extracted parent context, or null when
     *                          the pair fails the W3C trace-context grammar
     */
    public static function extract(string $traceParent, ?string $traceState = null): ?SpanContext
    {
        $value = mb_trim($traceParent);

        if (preg_match(self::TRACE_CONTEXT_GRAMMAR, $value, $matches) !== 1) {
            return null;
        }

        if (self::identityIsRefused($matches)) {
            return null;
        }

        return new SpanContext(
            mb_strtolower($matches[1]),
            mb_strtolower($matches[2]),
            (hexdec($matches[3]) & 1) === 1,
            $traceState,
        );
    }

    /**
     * Renders the context's own `traceparent` line -- the outbound
     * half of the pair, the exact shape the extract answers its
     * grammar against.
     */
    public static function inject(SpanContext $spanContext): string
    {
        return $spanContext->traceParent();
    }

    /**
     * Whether the grammar-carried identity the W3C context refuses:
     * the all-zero pair, or flag bits beyond the sampled bit alone.
     *
     * @param array<array-key, string> $matches the grammar's captured fields
     */
    private static function identityIsRefused(array $matches): bool
    {
        if ($matches[1] === str_repeat('0', 32)) {
            return true;
        }

        if ($matches[2] === str_repeat('0', 16)) {
            return true;
        }

        return (hexdec($matches[3]) & 0xFE) !== 0;
    }
}
