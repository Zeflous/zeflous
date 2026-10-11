<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The case-insensitive header name resolution.
 *
 * Headers are stored keyed by their original spelling; a lookup walks the
 * stored spellings comparing byte-wise with `strcasecmp`, which carries
 * the whole PSR-7 case-insensitivity contract for RFC 9110 token names
 * (the grammar rejects non-ASCII before any name can reach the table).
 */
final class HeaderIndex
{
    /**
     * @param array<string, list<string>> $headers
     *
     * @return null|string the stored spelling of the case-insensitive
     *                     name, or null when the header is absent
     */
    public static function storedName(array $headers, string $name): ?string
    {
        foreach (array_keys($headers) as $candidate) {
            if (strcasecmp($candidate, $name) === 0) {
                return $candidate;
            }
        }

        return null;
    }
}
