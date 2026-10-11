<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The header append mutation: adding values to a header that may
 * already exist under any spelling of its case-insensitive name.
 *
 * The only table mutation that reads existing values -- the append
 * carries the merge semantics, so it lives apart from the write-only
 * mutations in {@see HeaderTable}. Name resolution goes through
 * {@see HeaderIndex}.
 */
final class HeaderAppender
{
    /**
     * Appends the values to the values already stored under the
     * case-insensitive name (under the stored spelling), or stores the
     * values under this spelling when the header is absent.
     *
     * @param array<string, list<string>> $headers
     * @param list<string>                $values
     *
     * @return array<string, list<string>>
     */
    public static function appended(array $headers, string $name, array $values): array
    {
        $existingKey = HeaderIndex::storedName($headers, $name);
        if ($existingKey === null) {
            $headers[$name] = $values;

            return $headers;
        }

        foreach ($values as $value) {
            $headers[$existingKey][] = $value;
        }

        return $headers;
    }
}
