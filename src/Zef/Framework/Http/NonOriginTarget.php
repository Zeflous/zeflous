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
 *
 * The remaining non-origin shape is the authority form
 * (`example.com:443`, `[::1]:443`), which RFC 9112 §3.2.3 reserves for
 * the `CONNECT` tunnel ingress. This layer implements no tunnel
 * ingress, and `parse_url()` would silently discard the authority the
 * target names (the host and port vanish and the request re-emerges as
 * a root-path request it never was), so the authority form fails
 * closed with the layer's exception instead of being rewritten -- the
 * same honour-or-reject choice the reference grammar makes, answered
 * with reject.
 */
final class NonOriginTarget
{
    private const string MALFORMED_TARGET = 'Malformed request target.';

    /**
     * Splits a non-origin-form target into path and query.
     *
     * @param string $target the non-origin-form target, empty when the
     *                       request target itself is empty
     *
     * @return array{non-empty-string, string} the normalised path and the raw query
     *
     * @throws HttpException when the target carries the authority form
     */
    public static function split(string $target): array
    {
        if ($target !== '' && $target !== '*' && !str_contains($target, '://')) {
            throw new HttpException(self::MALFORMED_TARGET);
        }

        $parts = parse_url($target);
        $path = $parts['path'] ?? '';

        return [
            $path !== '' && $path[0] === '/' ? $path : '/',
            $parts['query'] ?? '',
        ];
    }
}
