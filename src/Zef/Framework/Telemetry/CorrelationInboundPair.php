<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The inbound correlation pair's own reading: the `traceparent` line
 * and its `tracestate` companion as the request carried them, read
 * into the {@see CorrelationIdentity} the propagation boundary
 * parents the request span with.
 *
 * The reading is tolerant because the pair is untrusted: a line that
 * is absent, oversized, or the wrong width, a grammar the W3C
 * trace-context does not spell, a companion the identity's own bounds
 * refuse -- each answers the null refusal, never an exception across
 * the boundary, the same posture the {@see CorrelationPropagator}'s
 * extract carries. The refusals are the unit's own three: the line
 * the wire would accept (the oversize and exact-width reads), the
 * identity the grammar spells (the internal throw the catch
 * swallows), and the constructor's own bounds -- the companion's
 * 512-byte ceiling among them.
 *
 * Extracted from the reference implementation's propagator (legacy
 * `zef-main` `Framework/Observability/CorrelationPropagator.php`)
 * verbatim in behaviour: the port reads the same tolerance as its
 * own unit -- the chain of refusals the cloud reviewer's width
 * policy reads as one method per concern -- with the one deviation
 * the propagator's own note records: the companion's fail-fast read
 * dropped behind the constructor's identical bound.
 */
final class CorrelationInboundPair
{
    /** The inbound line's W3C grammar, the fields the reading spells. */
    private const string TRACEPARENT_GRAMMAR
        = '/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/i';

    /**
     * Reads the inbound pair into the W3C identity -- or the null
     * refusal when the pair fails the bounds or the grammar.
     *
     * @param null|string $traceParent the `traceparent` line as received
     * @param null|string $traceState  the `tracestate` companion, when present
     *
     * @return null|CorrelationIdentity the read identity, or null when the pair fails the bounds or grammar
     */
    public static function identity(?string $traceParent, ?string $traceState): ?CorrelationIdentity
    {
        $line = self::acceptedLine($traceParent);

        if ($line === null) {
            return null;
        }

        try {
            return self::identityFromLine($line, $traceState);
        } catch (InvalidArgumentException) {
            // The untrusted pair's own refusal: an identity the bounds
            // or the grammar would reject is the null answer, never
            // the reading's exception.
            return null;
        }
    }

    /**
     * The line the wire would accept: the raw line inside its
     * ceiling, trimmed to the exact W3C render width -- or the null
     * refusal when either read fails.
     *
     * The null line and the empty line fold into the width refusal:
     * both trim to nothing, and nothing is never the fifty-five
     * characters the render spells.
     */
    private static function acceptedLine(?string $traceParent): ?string
    {
        if (mb_strlen((string) $traceParent) > CorrelationIdentity::MAX_TRACEPARENT_BYTES) {
            return null;
        }

        $value = mb_trim((string) $traceParent);

        return mb_strlen($value) === CorrelationIdentity::MAX_TRACEPARENT_BYTES ? $value : null;
    }

    /**
     * The identity the accepted line spells: the grammar's three
     * fields lower-cased, the companion riding along -- the internal
     * refusal the catch swallows when the grammar fails.
     */
    private static function identityFromLine(string $line, ?string $traceState): CorrelationIdentity
    {
        if (preg_match(self::TRACEPARENT_GRAMMAR, $line, $matches) !== 1) {
            throw new InvalidArgumentException('The inbound pair fails the W3C grammar.');
        }

        return new CorrelationIdentity(
            mb_strtolower($matches[1]),
            mb_strtolower($matches[2]),
            mb_strtolower($matches[3]),
            $traceState,
        );
    }
}
