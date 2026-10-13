<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The conditional-request precondition evaluation (RFC 9110 §13):
 * answers whether the request's validators are satisfied by the
 * response's, in the precedence the section fixes.
 *
 * `If-None-Match`, when present, is the only validator precondition
 * consulted -- `If-Modified-Since` is ignored even when `If-None-Match`
 * does not match (regression P-13, issue #171 in the reference lineage:
 * the reference once evaluated both, and an IMS-only request always paid
 * the full 200). An `If-None-Match` consisting only of optional
 * whitespace is an absent precondition. Ported from the reference
 * implementation (legacy `zef-framework`
 * `Adapters.Http.ETagMiddleware::matchesPrecondition`); the reference's
 * two IMS emptiness guards are folded away -- an empty date value
 * already fails {@see HttpDate}'s grammar, so the guards were
 * indistinguishable from their own removal.
 */
final class RequestPreconditions
{
    /**
     * Answers whether a body-less 304 is due for a response carrying the
     * given strong validator and `Last-Modified` timestamp.
     */
    public static function matches(
        ServerRequestInterface $serverRequest,
        string $etag,
        string $lastModified,
    ): bool {
        $ifNoneMatch = mb_trim($serverRequest->getHeaderLine('If-None-Match'));
        if ($etag !== '' && $ifNoneMatch !== '' && IfNoneMatch::matches($ifNoneMatch, $etag)) {
            return true;
        }

        return $ifNoneMatch === ''
            && IfModifiedSince::notModified(mb_trim($serverRequest->getHeaderLine('If-Modified-Since')), $lastModified);
    }
}
