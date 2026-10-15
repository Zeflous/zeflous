<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The list-knob reading the security families share: the two
 * spellings one list carries -- a native list of strings (the file's
 * declaration) or the comma-separated string an environment overlay
 * writes, trimmed with empty entries dropped (a trailing separator
 * is cosmetic, not an entry) -- the lineage the rate-limit family's
 * trusted-proxy list and the auth family's token table both follow,
 * extracted here now that the second family made the shape shared
 * property rather than a coincidence.
 *
 * The knob's full name travels as one label (`security.auth tokens`,
 * `security.rate_limit trusted_proxies`), exactly as each family's
 * own rejection spells it. The shape of each entry is the owning
 * family's own concern: this reader only carries the entries
 * through, exactly as spelled.
 */
final class ListKnobs
{
    /**
     * Reads one list knob's value.
     *
     * @param string $label the knob's full name, as the rejection
     *                      spells it (`security.auth tokens`)
     *
     * @return list<string>
     *
     * @throws HttpException when the value is neither a list of strings
     *                       nor a comma-separated string
     */
    public static function read(mixed $value, string $label): array
    {
        if (\is_array($value)) {
            return self::entries($value, $label);
        }

        if (\is_string($value)) {
            return self::commaSeparated($value);
        }

        throw new HttpException(
            'The ' . $label . ' knob must be a list of strings or a comma-separated string, '
            . get_debug_type($value) . ' given.',
        );
    }

    /**
     * The native list's entries, each proven a string as it is read.
     *
     * @param array<array-key, mixed> $value
     *
     * @return list<string>
     */
    private static function entries(array $value, string $label): array
    {
        $entries = [];

        foreach ($value as $entry) {
            if (!\is_string($entry)) {
                throw new HttpException(
                    'The ' . $label . ' knob must be a list of strings, '
                    . get_debug_type($entry) . ' given.',
                );
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * The comma-separated spelling's entries: each trimmed, the
     * empties dropped, the order kept.
     *
     * @return list<string>
     */
    private static function commaSeparated(string $value): array
    {
        $entries = array_map(mb_trim(...), explode(',', $value));

        return array_values(array_diff($entries, ['']));
    }
}
