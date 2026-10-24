<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The stateless cookie helper's request half: reading and writing one
 * named cookie through the request's own cookie bag -- the PSR-7 pair
 * (`getCookieParams()` / `withCookieParams()`) the vendored
 * {@see ServerRequestInterface} declares and no src consumer had yet
 * spelled.
 *
 * The read is fail-closed on shape: the bag's value must be a string,
 * because the SAPI decode only ever produces strings -- a non-string
 * member means a host-built or synthetic request, and silently
 * coercing it (`(string) 42`) would answer a value the client never
 * sent. An absent name answers null, the honest "no such cookie",
 * which the session middleware reads as "mint a fresh identifier"
 * and a host reads as "the cookie is not there yet".
 *
 * The write is the symmetric operation the helper's promise names
 * (a utility to READ AND WRITE cookies): one name, one value, folded
 * into a bag copy that carries every other cookie untouched --
 * exactly the PSR-7 wither discipline the request objects themselves
 * follow. Both names pass the token grammar at their own call sites:
 * a cookie name that is not an RFC 9110 token is a host bug, named
 * loudly, not a header the browser would silently drop.
 *
 * The response half of the helper's promise lives in {@see SetCookie}:
 * the request bag is what the client sent; the `Set-Cookie` header is
 * what the server answers, and the two halves never share a fold.
 */
final class RequestCookies
{
    /** The cookie-name grammar: an RFC 9110 token, as every
     * header-adjacent name in the layer reads. */
    private const string TOKEN_NAME_GRAMMAR = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D';

    /**
     * Reads one named cookie's value out of the request's cookie bag.
     *
     * @param ServerRequestInterface $serverRequest the request whose cookie bag
     *                                              is read
     * @param string                 $name          the cookie's name
     *
     * @return null|string the cookie's value, or null when the bag does
     *                     not carry the name
     *
     * @throws HttpException when the bag carries the name under a
     *                       non-string value
     */
    public static function value(ServerRequestInterface $serverRequest, string $name): ?string
    {
        $bag = $serverRequest->getCookieParams();

        if (!\array_key_exists($name, $bag)) {
            return null;
        }

        $value = $bag[$name];

        if (!\is_string($value)) {
            throw new HttpException(
                'The request cookie bag carries a non-string value under the name '
                . "'" . $name . "' (" . get_debug_type($value) . ').',
            );
        }

        return $value;
    }

    /**
     * Answers the request decorated with one named cookie written into
     * its cookie bag: every other cookie the bag carried stays
     * untouched, and an existing value under the same name is replaced
     * -- the wither discipline the request objects themselves follow.
     *
     * @param ServerRequestInterface $serverRequest the request whose cookie bag
     *                                              is written
     * @param string                 $name          the cookie's name
     * @param string                 $value         the cookie's value
     *
     * @throws HttpException when the name is not an RFC 9110 token, or
     *                       the value is not a legal cookie value
     */
    public static function with(
        ServerRequestInterface $serverRequest,
        string $name,
        string $value,
    ): ServerRequestInterface {
        self::assertCookieName($name);
        SetCookie::assertValueGrammar($value);

        $bag = $serverRequest->getCookieParams();

        return $serverRequest->withCookieParams([...$bag, $name => $value]);
    }

    /**
     * Holds the cookie-name grammar at the write's own call site.
     */
    private static function assertCookieName(string $name): void
    {
        if (preg_match(self::TOKEN_NAME_GRAMMAR, $name) !== 1) {
            throw new HttpException(
                'The cookie name must be an RFC 9110 token, '
                . "'" . $name . "' given.",
            );
        }
    }
}
