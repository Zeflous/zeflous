<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The request method grammar: one RFC 9110 token (the tchar class), so
 * the registered verbs and the extension methods (PROPFIND, QUERY, ...)
 * validate through the same single policy point. Pure static helper --
 * the same shape as the other HTTP grammars. The assertion fails closed
 * with the layer's exception, byte-identical to the reference
 * implementation (legacy `zef-framework`
 * `Domain\Validation\HttpMethodValidator`).
 */
final class HttpMethod
{
    private const string METHOD_PATTERN = "/^[!#$%&'*+.^_`|~0-9A-Za-z-]+\\z/";

    /**
     * @throws HttpException when the method is not a single valid token
     */
    public static function assert(string $method): void
    {
        if (preg_match(self::METHOD_PATTERN, $method) !== 1) {
            throw new HttpException("Invalid HTTP method '{$method}'.");
        }
    }
}
