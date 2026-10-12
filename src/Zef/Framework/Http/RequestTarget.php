<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\UriInterface;

/**
 * The request-target grammar and derivation: the target a request line
 * carries is either derived from the URI (origin-form -- the path with a
 * guaranteed leading slash plus the query) or set explicitly (origin-,
 * absolute- or asterisk-form), and a raw control byte anywhere in an
 * explicit target would corrupt the request line (request smuggling), so
 * it is rejected fail-closed with the layer's exception, byte-identical
 * to the reference implementation (legacy `zef-framework`
 * `RequestTrait::assertRequestTarget`). Pure static helper.
 */
final class RequestTarget
{
    private const string CONTROL_PATTERN = '/[\x00-\x1F\x7F]/';

    /**
     * @throws HttpException when the target carries a control character
     */
    public static function assert(string $requestTarget): void
    {
        if (preg_match(self::CONTROL_PATTERN, $requestTarget) === 1) {
            throw new HttpException('Invalid request target.');
        }
    }

    /**
     * Answers the effective request target: the explicit override when
     * one was set, the origin-form derivation from the URI otherwise.
     */
    public static function resolve(?string $requestTargetOverride, UriInterface $uri): string
    {
        return $requestTargetOverride ?? self::fromUri($uri);
    }

    /**
     * Derives the origin-form target from the URI: the path with a
     * guaranteed leading slash, plus the query when present. An empty
     * path answers the root slash, matching what a request line for the
     * origin's root carries.
     */
    private static function fromUri(UriInterface $uri): string
    {
        $path = $uri->getPath();
        if ($path === '') {
            $target = '/';
        } elseif ($path[0] === '/') {
            $target = $path;
        } else {
            $target = '/' . $path;
        }

        $query = $uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }

        return $target;
    }
}
