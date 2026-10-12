<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Splits an absolute-form or asterisk-form request target into its path
 * and query components.
 *
 * Only the non-slash-prefixed targets -- the absolute form
 * (`http://other.example.com/x?q=2`) and the asterisk form (`OPTIONS *`)
 * -- reach this collaborator, where `parse_url()` answers the path they
 * name. The answered path must be normalised back to the root slash
 * (an `OPTIONS *` with `Host: example.com` must not build
 * `http://example.com*`), and a target that names no path collapses to
 * the root. The reference implementation's derivation, move-verbatim.
 */
final class NonOriginTarget
{
    /**
     * Splits a non-origin-form target into path and query.
     *
     * @param string $target the non-origin-form target, empty when the
     *                       request target itself is empty
     *
     * @return array{non-empty-string, string} the normalised path and the raw query
     */
    public static function split(string $target): array
    {
        $parts = parse_url($target);
        $path = $parts['path'] ?? '';

        return [
            $path !== '' && $path[0] === '/' ? $path : '/',
            $parts['query'] ?? '',
        ];
    }
}
