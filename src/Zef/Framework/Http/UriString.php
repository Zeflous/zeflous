<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The full URI string renderer over one component set.
 *
 * Pure static helper for the PSR-7 {@see Uri} value object: the scheme
 * prefix, the authority (through {@see UriAuthority}) with the RFC 3986
 * rule that a path under an authority is empty or begins with "/", and
 * the query and fragment suffixes.
 */
final class UriString
{
    /**
     * @param array{
     *     scheme: string,
     *     userInfo: string,
     *     host: string,
     *     port: null|int,
     *     path: string,
     *     query: string,
     *     fragment: string,
     * } $components
     */
    public static function render(array $components): string
    {
        $uri = $components['scheme'] !== '' ? $components['scheme'] . ':' : '';
        $authority = UriAuthority::render($components);
        if ($authority !== '') {
            $uri .= '//' . $authority . self::prefixedPath($components['path']);
        } else {
            $uri .= $components['path'];
        }

        if ($components['query'] !== '') {
            $uri .= '?' . $components['query'];
        }

        if ($components['fragment'] !== '') {
            $uri .= '#' . $components['fragment'];
        }

        return $uri;
    }

    private static function prefixedPath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        return '/' . $path;
    }
}
