<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Basic HTTP Accept negotiation: whether an Accept header permits any of a
 * route's declared representations. The header's ranges are parsed by
 * {@see RouteAcceptRanges}, and one media type's verdict comes from
 * {@see RouteAcceptMatch}.
 *
 * @internal
 */
final class RouteAcceptNegotiator
{
    /**
     * An absent Accept header permits every representation declared by a
     * route, and so does a header that expresses nothing negotiable: a
     * value whose every range the parse skipped is a preference the sender
     * never spelled, not a rejection the whole route table must pay for
     * (issue #782) -- the docblock's own doctrine, "an untrusted header is
     * runtime data, not a defect", answered at the boundary it used to
     * stop short of. A well-formed all-media zero-quality range still
     * refuses everything, because the boundary is the grammar, not the
     * mood: one valid range decides the header, and only a header with
     * none does.
     *
     * @param list<string> $produces
     */
    public static function accepts(?string $accept, array $produces): bool
    {
        if ($produces === [] || $accept === null) {
            return true;
        }

        $ranges = RouteAcceptRanges::parse($accept);

        if ($ranges === []) {
            return true;
        }

        foreach ($produces as $produce) {
            $segments = explode('/', $produce, 2);
            if (\count($segments) !== 2) {
                throw new ConfigException(\sprintf(
                    'The route field "produces" contains an invalid media type "%s".',
                    $produce,
                ));
            }

            [$type, $subtype] = $segments;

            if (RouteAcceptMatch::accepts($ranges, $type, $subtype)) {
                return true;
            }
        }

        return false;
    }
}
