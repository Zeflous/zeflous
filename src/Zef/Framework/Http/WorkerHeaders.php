<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The worker payload's header map in the layer's canonical form.
 *
 * A worker runtime hands the application the wire's header lines as a
 * plain map -- names spelled in whatever case the runtime's own array
 * carries, values a bare string or a list, the two spellings the PSR-7
 * contract itself admits. The {@see ServerRequest} constructor would
 * judge the map through its own header store, but the worker folds
 * read the map BEFORE any request exists ({@see WorkerBody} reads the
 * body-describing claim, {@see WorkerCookies} the cookie line,
 * {@see WorkerUri} the host authority), so one fold owns the canonical
 * spelling they all read: names lowercased (the deterministic
 * representation {@see ServerHeaders} answers for the SAPI twin), an
 * integer key cast to its decimal spelling (a numeric name is a legal
 * token the grammar accepts, the store's own documented cast), and
 * every name and value judged by {@see HeaderGrammar} -- the RFC 9110
 * token and field-value grammars, non-string values rejected instead
 * of coerced, control bytes refused before they can ride a header
 * anywhere. The canonical map's own keys carry the engine's fold
 * honestly: a name that is itself a numeric string lands under its
 * integer engine form (the KDD-H10 claim, replayed on the name -- the
 * engine folds a numeric-string key on every insert, and no array in
 * PHP can observe the difference).
 *
 * Two spellings of one name (``Content-Type`` and ``content-type`` in
 * the same map) are the wire's own multi-value order in map form: the
 * lists merge in encounter order, exactly as two header lines of one
 * field would carry both values -- never a last-wins overwrite that
 * silently drops a value the client sent.
 */
final class WorkerHeaders
{
    /**
     * Normalises a raw worker header map into the canonical form.
     *
     * The value is deliberately mixed at the boundary -- the same
     * doctrine {@see HeaderGrammar::normalize()} states for the PSR-7
     * contract's own untyped parameter: the runtime checks are the
     * enforcement point, and a non-string value is rejected rather
     * than coerced.
     *
     * @param array<array-key, mixed> $raw the map the worker runtime
     *                                     hands over, names in any case,
     *                                     values string, list or the
     *                                     shape the grammar will refuse
     *
     * @return array<array-key, list<string>> lowercased names => validated
     *                                        value lists (a numeric name
     *                                        lands under its integer
     *                                        engine form -- the fold the
     *                                        engine itself performs)
     *
     * @throws HttpException when a name is not an RFC 9110 token, a value
     *                       is not a string or a value carries forbidden
     *                       control characters
     */
    public static function normalize(array $raw): array
    {
        $headers = [];

        foreach ($raw as $name => $value) {
            // The canonical spelling -- an integer key folds to its decimal form.
            $folded = mb_strtolower((string) $name);

            $headers[$folded] = [
                ...($headers[$folded] ?? []),
                ...HeaderGrammar::normalize($folded, $value),
            ];
        }

        return $headers;
    }

    /**
     * Answers the values stored under a canonical name, in wire order.
     *
     * @param array<array-key, list<string>> $headers the canonical map
     *                                                {@see normalize()} answers
     * @param string                         $name    the lowercased field name
     *
     * @return list<string> the values, empty when the map carries none
     */
    public static function values(array $headers, string $name): array
    {
        return $headers[$name] ?? [];
    }
}
