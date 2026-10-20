<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Resolves whether a request explicitly prefers HTML over JSON problem
 * details.
 *
 * Two guards the reference carried are dropped because each is
 * unreachable-in-effect behind the parser's own grammar (verified by
 * running the full suite under the manually applied mutations -- the
 * complete suite passes): the empty header needs no early answer because
 * the parser's qualities of the empty header is the empty map and both
 * sides fall through to their zero defaults, and the HTML side's
 * positivity needs no conjunct of its own because the quality grammar
 * admits no negative value -- an HTML read that outranks the JSON side is
 * positive by construction, so the comparison alone carries the contract.
 */
final class ErrorContentNegotiator
{
    public static function prefersHtml(string $accept): bool
    {
        $qualities = AcceptHeaderParser::qualities($accept);
        $html = self::qualityFor($qualities, 'text/html', 'text/*');
        $json = self::qualityFor($qualities, 'application/problem+json', 'application/json', 'application/*');

        return $html > $json;
    }

    /** @param array<string, float> $qualities */
    private static function qualityFor(array $qualities, string ...$mediaTypes): float
    {
        foreach ([...$mediaTypes, '*/*'] as $mediaType) {
            if (\array_key_exists($mediaType, $qualities)) {
                return $qualities[$mediaType];
            }
        }

        return 0.0;
    }
}
