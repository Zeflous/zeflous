<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The `If-None-Match` precondition grammar (RFC 9110 §13.1.2): a single
 * `*` matches any validator, and a comma-separated candidate list matches
 * when any candidate -- strong or weak (`W/"..."`, `w/"..."`) -- compares
 * equal to the response's validator under weak comparison (the weakness
 * marker and the quoting are stripped before comparing, and surrounding
 * whitespace around candidates is not part of the comparison).
 *
 * Ported from the reference implementation (legacy `zef-framework`
 * `Adapters.Http.ETagMiddleware::ifNoneMatchMatches` + `stripWeakness`),
 * move-verbatim.
 */
final class IfNoneMatch
{
    /**
     * Answers whether the request's `If-None-Match` value is satisfied by
     * the response's strong validator.
     */
    public static function matches(string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === '*') {
            return true;
        }

        $canonical = self::withoutWeakness($etag);
        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = mb_trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if (self::withoutWeakness($candidate) === $canonical) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strips the weakness marker (either casing) and the entity quoting
     * from an ETag candidate, leaving the bare opaque validator.
     */
    private static function withoutWeakness(string $etag): string
    {
        $etag = mb_trim($etag);
        if (str_starts_with($etag, 'W/') || str_starts_with($etag, 'w/')) {
            $etag = mb_substr($etag, 2);
        }

        return mb_trim($etag, '"');
    }
}
