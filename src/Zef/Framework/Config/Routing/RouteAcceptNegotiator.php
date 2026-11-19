<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

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
     * A produces entry the compile grammar never admits (issue #799,
     * finding 1) contributes nothing here: the media type grammar is
     * the compile seam's own judgement ({@see RouteProduces} refuses a
     * produces entry that is not a type/subtype pair before any route
     * exists), so the match-time walk never meets one -- and the arm
     * the first spelling threw from was dead code wearing a contract
     * that contradicted the match pipeline's own doctrine
     * (match-time inputs never raise). The dead arm answers the same
     * doctrine the skipped ranges do: a produce the grammar never
     * spelled expresses nothing, so the verdict reads the well-formed
     * half alone.
     *
     * @param list<string> $produces
     */
    public static function accepts(?string $accept, array $produces): bool
    {
        if ($produces === [] || $accept === null) {
            return true;
        }

        $ranges = RouteAcceptRanges::parse($accept);

        // The empty parse seeds the verdict permitted -- the boundary of
        // issue #782, folded into the accumulator instead of a fourth
        // return: a header that expressed nothing permits everything, and
        // only a declared media type no parsed range permits can change
        // that answer, so the return reads the accumulated verdict alone.
        $permitted = $ranges === [];

        foreach ($produces as $produce) {
            $segments = explode('/', $produce, 2);

            if (\count($segments) !== 2) {
                continue;
            }

            [$type, $subtype] = $segments;

            $permitted = $permitted || RouteAcceptMatch::accepts($ranges, $type, $subtype);
        }

        return $permitted;
    }
}
