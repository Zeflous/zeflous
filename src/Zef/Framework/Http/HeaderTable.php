<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The write-only header table mutations: replace and remove over the
 * raw map, each returning the new map.
 *
 * Pure static engine over one `array<string, list<string>>` -- the same
 * depth-split the radix engine uses to stay under the architecture
 * gate's difficulty budget. Name resolution goes through
 * {@see HeaderIndex}; the merge-carrying append lives in
 * {@see HeaderAppender}, and value validation is the caller's contract.
 */
final class HeaderTable
{
    /**
     * Replaces any values stored under the case-insensitive name and
     * stores the new values under this spelling of the name.
     *
     * @param array<string, list<string>> $headers
     * @param list<string>                $values
     *
     * @return array<string, list<string>>
     */
    public static function replaced(array $headers, string $name, array $values): array
    {
        $storedKey = HeaderIndex::storedName($headers, $name);
        if ($storedKey !== null) {
            unset($headers[$storedKey]);
        }

        $headers[$name] = $values;

        return $headers;
    }

    /**
     * Removes the header stored under the case-insensitive name; an
     * absent name leaves the table unchanged.
     *
     * @param array<string, list<string>> $headers
     *
     * @return array<string, list<string>>
     */
    public static function removed(array $headers, string $name): array
    {
        $matchedKey = HeaderIndex::storedName($headers, $name);
        if ($matchedKey !== null) {
            unset($headers[$matchedKey]);
        }

        return $headers;
    }
}
