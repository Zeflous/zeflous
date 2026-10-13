<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The strong validator of a representation (RFC 9110 §8.8): the SHA-256
 * digest of the representation data, hex-encoded and quoted -- the exact
 * wire form an opaque strong ETag takes. An empty representation carries
 * no computed validator (an empty string answers "absent" to the
 * conditional-GET grammar).
 *
 * Ported from the reference implementation (legacy `zef-framework`
 * `Adapters.Http.ETagMiddleware::resolvePreconditions`).
 */
final class StrongEtag
{
    private const string HASH_ALGORITHM = 'sha256';

    /**
     * Answers the strong validator of the representation data, or an
     * empty string when the body is empty.
     */
    public static function of(string $body): string
    {
        return $body === '' ? '' : '"' . bin2hex(hash(self::HASH_ALGORITHM, $body, true)) . '"';
    }
}
