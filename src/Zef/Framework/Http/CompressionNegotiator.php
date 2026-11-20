<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The compression family's negotiator: the coding the response should
 * carry, answered from three honest filters -- the roadmap's own
 * preference order, what the client accepts, and what this runtime
 * can produce.
 *
 * The walk is the standard's own (RFC 9110 section 12.5 -- issue
 * #801's finding 5: the first spelling walked the server's
 * preference order and answered the first coding above zero, so a
 * client spelling `deflate;q=1, gzip;q=0.1` was answered gzip, the
 * server's preference overriding the client's explicit quality --
 * the deviation the first docblock recorded as deliberate): the
 * client's granted quality decides -- the producible coding whose
 * granted quality is the highest wins, the server's preference
 * order breaking ties alone (two codings granted the same quality
 * answer the one the server prefers, the only voice the standard
 * leaves the origin). A coding's granted quality is its own
 * spelling's `q=` when the header names it, the `*` wildcard's when
 * it does not, so under `Accept-Encoding: deflate, *;q=0.5` the
 * deflate preference's own 1.0 wins over the gzip the wildcard
 * would grant at 0.5 -- quality arithmetic decides, the client's
 * voice the standard gives it; a coding the client spelled `q=0`
 * is refused even when the wildcard grants it (`gzip;q=0, *` does
 * not negotiate gzip), and an absent or empty header accepts
 * nothing but identity -- the honest posture: a client that sent
 * no `Accept-Encoding` line is a client that decodes nothing.
 *
 * `identity` is deliberately NOT negotiated: this family's identity
 * answer is the pass-through the middleware returns unchanged, and a
 * client that refuses identity outright (`identity;q=0`) is still
 * served identity rather than a 406 -- the refusal the roadmap's
 * Phase 3.2 does not ask for and the reference engines do not send.
 * The decision is recorded in the family's KDD note.
 */
final readonly class CompressionNegotiator
{
    /** @var list<string> the codings this runtime can produce */
    private array $capabilities;

    /**
     * @param null|list<string> $capabilities the codings this runtime can
     *                                        produce; null reads the
     *                                        engine's own probe
     */
    public function __construct(?array $capabilities = null)
    {
        $this->capabilities = $capabilities ?? CompressionCapabilities::detect();
    }

    /**
     * Negotiates the response's content coding for one request's
     * `Accept-Encoding` line.
     *
     * @param string $acceptEncoding the raw header line, empty when absent
     *
     * @return null|string the coding to compress with, or null when the
     *                     honest answer is identity
     */
    public function negotiate(string $acceptEncoding): ?string
    {
        $qualities = AcceptEncodingQualities::qualities($acceptEncoding);

        // The client's quality decides, the server's preference
        // breaking ties alone: the walk stays in preference order (the
        // first coding of a tied quality is the preferred one) but
        // answers the coding whose granted quality is the highest,
        // never the first coding merely above zero.
        $best = null;
        $bestQuality = 0.0;

        foreach (ContentCoding::preferences($this->capabilities) as $coding) {
            $quality = $qualities[$coding] ?? $qualities['*'] ?? 0.0;

            if ($quality <= $bestQuality) {
                continue;
            }

            $best = $coding;
            $bestQuality = $quality;
        }

        return $best;
    }
}
