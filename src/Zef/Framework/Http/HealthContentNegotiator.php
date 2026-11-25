<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Resolves whether a request's Accept line explicitly prefers the
 * health surface's Prometheus exposition over its JSON -- the
 * health-as-gauges render's own consumer judgement, the error
 * boundary's {@see ErrorContentNegotiator} lineage replayed for the
 * metrics family's second representation ({@see
 * \Zef\Framework\Health\HealthExposition::render()}).
 *
 * THE JSON IS THE DEFAULT, and this judgement never changes that: the
 * health surface's contract is the load balancer's poll, and the
 * representation a poll reads is the JSON the surface has always
 * answered -- an absent header, a wildcard-only header, a garbage
 * header or a tie all fail the strict comparison and answer JSON, so
 * no load balancer ever meets a representation it did not ask for.
 * The gauges are armed only by a header that EXPLICITLY outranks the
 * JSON vocabulary on the text side: a Prometheus instance's own
 * Accept line (`text/plain;version=0.0.4;q=0.5` above the universal
 * wildcard's 0.1) carries the text spelling at a quality the
 * universal wildcard does not reach, so the collector scraping the
 * health surface reads the gauges over the same endpoint the poll
 * answers -- no second endpoint, no second probe pass.
 *
 * THE VOCABULARY IS THE SURFACE'S OWN: the exposition side reads
 * `text/plain` (the exposition's media type) and its `text/*`
 * wildcard; the JSON side reads `application/json` (the health
 * answer's own content type) and its `application/*` wildcard; the
 * universal wildcard is the shared floor both sides fold to when the
 * header names neither family -- which is exactly why a lone
 * universal wildcard is a tie, and a tie answers the default. The
 * most specific
 * spelling decides a side's quality (the {@see
 * AcceptHeaderParser}'s own grammar, the RFC 7231 specificity: a
 * `text/plain;q=0.3` under a `text/*;q=0.9` stays 0.3 -- the client's
 * voice for exactly this representation), so the coalescing order is
 * the specific type, the family wildcard, the universal floor.
 *
 * The parser is the shared bounded seam the error boundary's
 * negotiator already rides ({@see AcceptHeaderParser}: the 8192-byte
 * read, the lowercase fold, the malformed range skipped) -- this
 * judgement adds no parsing of its own, and a header too broken to
 * parse carries no preference at all.
 */
final class HealthContentNegotiator
{
    /**
     * Whether the Accept line explicitly prefers the exposition's
     * text format over the health JSON.
     *
     * @param string $accept the raw Accept header line, empty when
     *                       the request carries none
     */
    public static function prefersExposition(string $accept): bool
    {
        $qualities = AcceptHeaderParser::qualities($accept);

        $exposition = $qualities['text/plain']
            ?? $qualities['text/*']
            ?? $qualities['*/*']
            ?? 0.0;
        $json = $qualities['application/json']
            ?? $qualities['application/*']
            ?? $qualities['*/*']
            ?? 0.0;

        return $exposition > $json;
    }
}
