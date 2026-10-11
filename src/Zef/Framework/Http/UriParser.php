<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The URI parse pipeline: guards, engine parse, normalization and
 * percent-encoding of every component.
 *
 * Pure static helper over one URI string -- the parse counterpart of the
 * withers' per-component guards, so both routes produce identically
 * validated and encoded components.
 */
final class UriParser
{
    /**
     * Parse + validate + percent-encode a URI string into its components.
     *
     * @return array{
     *     scheme: string,
     *     userInfo: string,
     *     host: string,
     *     port: null|int,
     *     path: string,
     *     query: string,
     *     fragment: string,
     * }
     */
    public static function parse(string $uri): array
    {
        UriGrammar::assertNoControls($uri, 'URI');
        $parts = parse_url($uri);
        if ($parts === false) {
            throw new HttpException("Unable to parse URI '{$uri}'.");
        }

        $scheme = UriGrammar::normalizeScheme($parts['scheme'] ?? '');
        $host = UriHostGrammar::sanitize($parts['host'] ?? '');
        $port = $parts['port'] ?? null;
        UriGrammar::assertPort($port);

        return [
            'scheme' => $scheme,
            'userInfo' => UriEncoder::encodeUserInfo(
                $parts['user'] ?? '',
                $parts['pass'] ?? null,
            ),
            'host' => $host,
            'port' => $port,
            'path' => UriEncoder::encodeComponent($parts['path'] ?? '', UriEncoder::PATH_ALLOWED),
            'query' => UriEncoder::encodeComponent($parts['query'] ?? '', UriEncoder::QUERY_FRAGMENT_ALLOWED),
            'fragment' => UriEncoder::encodeComponent(
                $parts['fragment'] ?? '',
                UriEncoder::QUERY_FRAGMENT_ALLOWED,
            ),
        ];
    }
}
