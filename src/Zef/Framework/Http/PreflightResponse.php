<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The preflight short-circuit: the response a CORS preflight is answered
 * with, or null when the request must continue through the handler.
 *
 * A preflight -- an OPTIONS request carrying the
 * `Access-Control-Request-Method` marker ({@see PreflightRequest}) on an
 * armed policy -- never reaches the handler: an allowed `Origin` is
 * granted with a 204 carrying the full `Access-Control-Allow-*` answer
 * (the echoed origin, the method and header grants, and the
 * `Access-Control-Max-Age` the browser may cache the answer for, plus
 * `Vary: Origin` so a shared cache does not serve one origin's grant to
 * another); a present but unallowed origin is denied with the bare
 * `Vary` 204 -- no `Access-Control-Allow-*` header at all, which is what
 * makes the browser refuse the cross-origin use (the CORS failure mode
 * is the absence of the grant, not an error status); and a request with
 * no `Origin` at all falls through to the handler, there being nobody to
 * allow.
 *
 * Attribution: legacy `zef-framework` `Middleware/CorsMiddleware.php`
 * (`preflightResponse()` and `disallowedPreflightResponse()`),
 * move-verbatim behaviour, restructured for observability: the reference
 * re-read the `Origin` header inside separate helpers; here it is read
 * once, and the present-but-unallowed branch is judged from that single
 * value.
 */
final class PreflightResponse
{
    /**
     * Answers the response a preflight deserves, or null when the
     * request must continue through the handler (the policy is unarmed,
     * the request is not a preflight, or no `Origin` travels with it).
     *
     * @param string $allowMethods the `Access-Control-Allow-Methods` answer
     * @param string $allowHeaders the `Access-Control-Allow-Headers` answer
     */
    public static function answer(
        CorsPolicy $corsPolicy,
        ServerRequestInterface $serverRequest,
        string $allowMethods,
        string $allowHeaders,
    ): ?ResponseInterface {
        if (!$corsPolicy->isEnabled() || !PreflightRequest::isPreflight($serverRequest)) {
            return null;
        }

        $origin = mb_trim($serverRequest->getHeaderLine('Origin'));
        $echo = $corsPolicy->echoFor($origin);

        if ($echo !== null) {
            return self::granted($echo, $allowMethods, $allowHeaders);
        }

        if ($origin !== '') {
            return self::denied();
        }

        return null;
    }

    /**
     * Builds the 204 that grants a preflight's requested cross-origin
     * use.
     *
     * @param string $echo         the `Access-Control-Allow-Origin` value to echo
     * @param string $allowMethods the `Access-Control-Allow-Methods` answer
     * @param string $allowHeaders the `Access-Control-Allow-Headers` answer
     */
    private static function granted(string $echo, string $allowMethods, string $allowHeaders): Response
    {
        return new Response(204, [
            'Access-Control-Allow-Origin' => $echo,
            'Access-Control-Allow-Methods' => $allowMethods,
            'Access-Control-Allow-Headers' => $allowHeaders,
            'Access-Control-Max-Age' => '600',
            'Vary' => 'Origin',
        ]);
    }

    /**
     * Builds the bare `Vary` 204 a present but unallowed origin is
     * answered with.
     */
    private static function denied(): Response
    {
        return new Response(204, ['Vary' => 'Origin']);
    }
}
