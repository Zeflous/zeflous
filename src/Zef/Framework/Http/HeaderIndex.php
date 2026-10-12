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
 * Numeric token names are the engine's own edge: PHP folds a
 * numeric-string key to an integer on every insert, so a stored `123`
 * arrives here as the integer key and is compared through its digits --
 * the same key-folding honesty the superglobal factory applies at its
 * bag seams (KDD-H10).
 */
final class HeaderIndex
{
    /**
     * @param array<array-key, list<string>> $headers the stored rows; a
     *                                                numeric name is held as the engine's integer key
     * @param string                         $name    the name to resolve
     *
     * @return null|string the stored spelling of the case-insensitive
     *                     name, or null when the header is absent; a folded
     *                     integer key answers its digit spelling, which
     *                     addresses the same row again on every read
     */
    public static function storedName(array $headers, string $name): ?string
    {
        foreach (array_keys($headers) as $candidate) {
            if (strcasecmp((string) $candidate, $name) === 0) {
                return (string) $candidate;
            }
        }

        return null;
    }
}
