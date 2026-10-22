<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

/**
 * The deprecation link's response carriage: one question -- how does the
 * route's declared deprecation documentation link join the links the
 * response already carries?
 *
 * RFC 8288 link fields are list-valued by construction, so the link is
 * added beside whatever links the response already carries -- replacing
 * would erase the handler's own `rel="self"` links -- skipping the
 * addition only when the same target with the `deprecation` relation is
 * already there (compared case-insensitively through the Unicode case
 * map, the way link relations and schemes are compared in practice).
 *
 * @internal
 */
final class DeprecationLinkHeader
{
    /**
     * Appends the deprecation link to the response's Link field, unless
     * the same target and relation already ride it.
     *
     * @param non-empty-string $link the deprecation documentation URI-reference
     */
    public static function append(ResponseInterface $response, string $link): ResponseInterface
    {
        $value = '<' . $link . '>; rel="deprecation"';

        foreach ($response->getHeader('Link') as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($value)) {
                return $response;
            }
        }

        $message = $response->withAddedHeader('Link', $value);
        if (!$message instanceof ResponseInterface) {
            throw new UnexpectedValueException('The response header operation did not return a ResponseInterface.');
        }

        return $message;
    }
}
