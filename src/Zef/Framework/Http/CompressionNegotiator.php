<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The compression family's negotiator: the coding the response should
 * carry, answered from three honest filters -- the roadmap's own
 * preference order, what the client accepts, and what this runtime
 * can produce.
 *
 * The walk is the standard's own: the preference order is walked
 * best-first, and the first coding whose granted quality is above
 * zero wins -- exactly as an HTTP origin server walks its own
 * representation table. A coding's granted quality is its own
 * spelling's `q=` when the header names it, the `*` wildcard's when
 * it does not, so under `Accept-Encoding: deflate, *;q=0.5` the
 * gzip preference survives on the wildcard's 0.5 while the deflate
 * preference's own 1.0 never gets its turn -- preference order, not
 * quality arithmetic, decides.
 *
 * A coding the client spelled `q=0` is refused even when the
 * wildcard grants it (`gzip;q=0, *` does not negotiate gzip), and an
 * absent or empty header accepts nothing but identity -- the
 * legacy-engines' own posture, and the honest one: a client that
 * sent no `Accept-Encoding` line is a client that decodes nothing.
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

        foreach (ContentCoding::preferences($this->capabilities) as $coding) {
            $quality = $qualities[$coding] ?? $qualities['*'] ?? 0.0;

            if ($quality > 0.0) {
                return $coding;
            }
        }

        return null;
    }
}
