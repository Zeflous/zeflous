<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Derives the URI path and query from the raw request target a SAPI reports.
 *
 * `REQUEST_URI` carries the request-line target verbatim, which is not
 * always an origin-form path: an absolute-form or asterisk-form target
 * must collapse to the path it names ({@see NonOriginTarget}), and a
 * fragment suffix is legally part of the target even though it never
 * travels. The split deliberately avoids `parse_url()` for origin-form
 * input: a target such as `//foo/bar` is a legal absolute-path
 * reference that `parse_url()` would reinterpret as a network-path
 * (host `foo`) and silently corrupt routing. The raw target stays
 * verbatim in the request's own target override; only the URI's
 * path/query components are derived here. The front-controller
 * script-prefix policy lives in {@see FrontController}.
 */
final class ServerPath
{
    /**
     * Splits a raw request target into its path and query components.
     *
     * @param string $target the raw `REQUEST_URI` value, empty when absent
     *
     * @return array{non-empty-string, string} the path (leading slash guaranteed) and the raw query
     */
    public static function split(string $target): array
    {
        if (!str_starts_with($target, '/')) {
            return NonOriginTarget::split($target);
        }

        $query = '';
        $queryPosition = mb_strpos($target, '?');

        if ($queryPosition !== false) {
            $query = mb_substr($target, $queryPosition + 1);
            $target = mb_substr($target, 0, $queryPosition);
        }

        $fragmentPosition = mb_strpos($target, '#');

        if ($fragmentPosition !== false) {
            $target = mb_substr($target, 0, $fragmentPosition);
        }

        return [$target === '' ? '/' : $target, $query];
    }
}
