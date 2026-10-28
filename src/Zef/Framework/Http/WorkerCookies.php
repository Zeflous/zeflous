<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The cookie bag fold: the wire's `Cookie` header values decoded into
 * the request's cookie parameters.
 *
 * The SAPI engine builds `$_COOKIE` from the `Cookie` header before the
 * application runs; a worker runtime hands the header still on the
 * wire. This fold replays the pair grammar the reference implementation
 * spelled and the CSRF family already carries ({@see CsrfCookieValue},
 * move-verbatim): pairs split on `;` and whitespace-trimmed, each pair
 * split on the first `=` only (values may themselves carry `=`), the
 * pair's value trimmed. The grammar's honest readings stay: a bare
 * token with no `=` answers the empty-string value (a valueless cookie
 * is distinct from an absent one), and a name keeps its own bytes --
 * a spaced spelling around the `=` is the sender's own, and the bag
 * never invents a cleaner name for it.
 *
 * Two readings the wire can legally carry are merged rather than
 * refused: a name that repeats (within one value or across several
 * `Cookie` values) answers its LAST occurrence -- the fresh value
 * shadows the stale one, the same shadow order the reference's
 * left-to-right scan resolves. A pair with an empty name contributes
 * nothing (a nameless pair describes no cookie), and multiple `Cookie`
 * header values merge pair-wise in wire order rather than through the
 * comma join that would corrupt the pair grammar.
 */
final class WorkerCookies
{
    /**
     * Decodes the Cookie header values into the cookie bag.
     *
     * @param list<string> $cookieValues the wire's `Cookie` header
     *                                   values, in wire order
     *
     * @return array<array-key, string> the decoded cookie bag (a
     *                                  numeric-string name lands under
     *                                  its integer engine form -- the
     *                                  same fold the engine's own
     *                                  `$_COOKIE` build performs)
     */
    public static function bag(array $cookieValues): array
    {
        $bag = [];

        foreach ($cookieValues as $cookieValue) {
            foreach (explode(';', $cookieValue) as $pair) {
                // The pair splits on the first `=` only -- the limit owns
                // that grammar, and the pad below only defaults the arm a
                // valueless pair never carried.
                $parts = explode('=', mb_trim($pair), 2);
                [$name, $value] = array_pad($parts, 2, '');

                if ($name === '') {
                    continue;
                }

                $bag[$name] = mb_trim($value);
            }
        }

        return $bag;
    }
}
