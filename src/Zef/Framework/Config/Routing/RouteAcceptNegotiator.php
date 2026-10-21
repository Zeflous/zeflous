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
     * An absent Accept header permits every representation declared by a route.
     *
     * @param list<string> $produces
     */
    public static function accepts(?string $accept, array $produces): bool
    {
        if ($produces === [] || $accept === null || mb_trim($accept) === '') {
            return true;
        }

        $ranges = RouteAcceptRanges::parse($accept);

        foreach ($produces as $mediaType) {
            $segments = explode('/', $mediaType, 2);
            if (\count($segments) !== 2) {
                throw new ConfigException(\sprintf('The route field "produces" contains an invalid media type "%s".', $mediaType));
            }

            [$type, $subtype] = $segments;

            if (RouteAcceptMatch::accepts($ranges, $type, $subtype)) {
                return true;
            }
        }

        return false;
    }
}
