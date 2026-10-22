<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;
use Zef\Framework\Http\HttpDate;

/**
 * Renders one matched route's deprecation declaration onto its response:
 * the RFC 9745 `Deprecation` field (the deprecation instant, as the
 * structured-field date spelling `@<unix time>`), the RFC 8594 `Sunset`
 * field (the retirement instant, as the HTTP-date spelling), and the RFC
 * 8288 `Link` field with the `deprecation` relation RFC 9745 registers
 * (the documentation link, `<...>; rel="deprecation"`).
 *
 * The route declaration is the default posture; the handler's own answer
 * wins where it carries one. `Deprecation` and `Sunset` are single-valued
 * fields, so the declaration fills them only when the response does not
 * already carry them -- a handler that answers its own deprecation dates
 * keeps them, exactly like a handler's own `Vary` tokens survive the
 * route-level `Vary: Accept` fold. `Link` is a list-valued field, so the
 * deprecation link is added beside whatever links the response already
 * carries ({@see DeprecationLinkHeader} owns that fold's semantics).
 *
 * Called from the terminal dispatch ({@see RouteDispatchHandler}) on the
 * executed representation, so the headers ride every response the route
 * answers -- including the conditional lane's bodyless 304s, which keep
 * the headers ({@see NotModifiedResponse} rewrites status and body only)
 * so a cached representation's lifecycle metadata stays current.
 *
 * @internal
 */
final class RouteDeprecationHeaders
{
    /**
     * Applies the deprecation headers a route's declaration carries onto
     * its executed response; an undeclared (null) deprecation returns the
     * response untouched.
     *
     * @param null|array{
     *     date: int,
     *     sunset: null|int,
     *     link: null|non-empty-string,
     * } $deprecation the route's canonicalised declaration
     */
    public static function apply(ResponseInterface $response, ?array $deprecation): ResponseInterface
    {
        if ($deprecation === null) {
            return $response;
        }

        $response = self::fill($response, 'Deprecation', '@' . $deprecation['date']);

        if ($deprecation['sunset'] !== null) {
            $response = self::fill($response, 'Sunset', HttpDate::render($deprecation['sunset']));
        }

        if ($deprecation['link'] !== null) {
            return DeprecationLinkHeader::append($response, $deprecation['link']);
        }

        return $response;
    }

    /**
     * Fills a single-valued header only when the response does not carry
     * it: the handler's own answer to that header wins over the route's
     * default posture.
     *
     * @param non-empty-string $name  the header's name
     * @param string           $value the header's value, non-empty where this is called
     *                                (a date spelling or an `@`-prefixed instant)
     */
    private static function fill(ResponseInterface $response, string $name, string $value): ResponseInterface
    {
        if ($response->getHeaderLine($name) !== '') {
            return $response;
        }

        $message = $response->withHeader($name, $value);
        if (!$message instanceof ResponseInterface) {
            throw new UnexpectedValueException('The response header operation did not return a ResponseInterface.');
        }

        return $message;
    }
}
