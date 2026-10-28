<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The query bag fold: the URI's query component decoded into the
 * request's query parameters.
 *
 * The SAPI engine decodes the query string into `$_GET` before the
 * application ever runs; a worker runtime hands the query still on the
 * wire, percent-encoded inside the URI. This fold replays the engine's
 * own decode (`parse_str()`, the routine the SAPI's `$_GET` build
 * itself rides), so the bag a worker-built request carries answers the
 * same shapes the superglobal twin would: numeric-string keys fold to
 * their integer engine form (the KDD-H10 claim stated at the
 * superglobal boundary, replayed here -- a `0=` pair answers under the
 * integer key `0`, and the string-keyed and integer-keyed views of the
 * same bag are observationally identical), bracketed names nest into
 * arrays, and the engine's own `max_input_vars` ceiling governs the
 * decode the same way it governs the SAPI's.
 *
 * An empty query component answers the empty bag -- absence, never a
 * bag carrying a decoded empty string.
 */
final class WorkerQuery
{
    /**
     * Decodes the query component into the query bag.
     *
     * @param string $query the URI's query component, percent-encoded
     *                      as the wire carried it
     *
     * @return array<array-key, mixed> the decoded query bag (a
     *                                 numeric-string name lands under its
     *                                 integer engine form -- the fold the
     *                                 engine itself performs)
     */
    public static function bag(string $query): array
    {
        /** @var array<array-key, mixed> $decoded the engine's query decode */
        $decoded = [];
        parse_str($query, $decoded);

        return $decoded;
    }
}
