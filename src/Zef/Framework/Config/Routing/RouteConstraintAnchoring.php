<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The constraint pattern's own anchoring fold: the one question the
 * declarations' validation asks of a declared pattern -- how does it
 * ride inside the fully anchored spelling the match judges segments
 * with (`^(?:pattern)\z`)?
 *
 * THE DELIMITER ESCAPE (issue #799, finding 2): a declared pattern
 * may carry a `/` of its own -- a regex that legitimately matches a
 * slash -- and the anchored spelling wraps every pattern in the `/`
 * delimiter verbatim at its own risk: the first `/` inside the
 * pattern would close the delimiter early and the compilability
 * probe would refuse a pattern the engine could have compiled, with
 * a message that named no delimiter. The anchoring walk escapes
 * every delimiter the pattern carries that no preceding backslash
 * already escaped, so the probe's refusal is the pattern's own
 * grammar and never the wrapper's spelling. The walk is byte-wise
 * because the PCRE grammar is: a backslash escapes exactly the byte
 * that follows it, so an even run of backslashes leaves the
 * following `/` unescaped (the delimiter to escape) and an odd run
 * has already escaped it (the pattern's own spelling, kept
 * verbatim).
 *
 * The walk splits by BYTE -- the PCRE grammar's own unit -- and
 * the house's byte-semantics doctrine spells it `mb_str_split()`
 * carrying the '8bit' encoding (the single-byte map where every
 * byte IS one character, the MbStrFunctions doctrine's own way of
 * owning byte semantics; the character-map default would group a
 * multi-byte sequence into one step, and a broken lead byte could
 * swallow the very backslash the PCRE grammar counts).
 *
 * @internal consumed through {@see RouteConstraintDeclarations}
 */
final readonly class RouteConstraintAnchoring
{
    /**
     * Wraps one declared pattern in its fully anchored,
     * delimiter-escaped spelling.
     *
     * @return non-empty-string the anchored pattern the match judges with
     */
    public static function anchored(string $pattern): string
    {
        $wrapped = '/^(?:';
        $backslashes = 0;

        foreach (mb_str_split($pattern, 1, '8bit') as $byte) {
            if ($byte === '\\') {
                ++$backslashes;
                $wrapped .= $byte;

                continue;
            }

            if ($byte === '/' && $backslashes % 2 === 0) {
                $wrapped .= '\\';
            }

            $backslashes = 0;
            $wrapped .= $byte;
        }

        return $wrapped . ')\z/';
    }
}
