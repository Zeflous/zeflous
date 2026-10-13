<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The CORS decoration of an ordinary response: the allow set stamped onto
 * it and the `Vary` that grew to include `Origin`.
 *
 * The three `Access-Control-Allow-*` headers the reference stamped on
 * every decorated response travel along verbatim -- the origin echo, the
 * method grant and the header grant -- so a simple cross-origin request
 * (one that needed no preflight) still reads its grant off the response.
 * `Vary` is merged rather than replaced: whatever the handler already
 * varied on stays, because a response that varies on `Accept-Encoding`
 * AND on the origin must not be cached as if it varied on either alone.
 *
 * Attribution: legacy `zef-framework` `Middleware/CorsMiddleware.php`
 * (`decorateWithCorsHeaders()`), move-verbatim behaviour; the merged
 * token walk lives in {@see VaryTokens}.
 */
final class CorsHeaders
{
    /**
     * Stamps the allow set and the origin-aware `Vary` onto a response.
     *
     * @param string $echo         the `Access-Control-Allow-Origin` value to echo
     * @param string $allowMethods the `Access-Control-Allow-Methods` answer
     * @param string $allowHeaders the `Access-Control-Allow-Headers` answer
     */
    public static function stamp(
        ResponseInterface $response,
        string $echo,
        string $allowMethods,
        string $allowHeaders,
    ): ResponseInterface {
        $message = $response
            ->withHeader('Access-Control-Allow-Origin', $echo)
            ->withHeader('Access-Control-Allow-Methods', $allowMethods)
            ->withHeader('Access-Control-Allow-Headers', $allowHeaders)
        ;
        \assert($message instanceof ResponseInterface, 'the with* chain must preserve the response type.');

        $stamped = $message->withHeader('Vary', implode(', ', VaryTokens::merged($message)));
        \assert($stamped instanceof ResponseInterface, 'the with* chain must preserve the response type.');

        return $stamped;
    }
}
