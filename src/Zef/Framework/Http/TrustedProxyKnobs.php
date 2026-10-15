<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The trusted-proxy list knob's reading: the two spellings one list
 * carries -- a native list of strings (the file's declaration, every
 * entry an exact address or a CIDR network as {@see TrustedProxyMatcher}
 * reads them) or the comma-separated string an environment overlay
 * writes (`APP_SECURITY__RATE_LIMIT__TRUSTED_PROXIES=10.0.0.0/8,
 * 127.0.0.1`), trimmed with empty entries dropped (a trailing separator
 * is cosmetic, not an entry).
 *
 * Anything else is a configuration error with the knob named: a list
 * carrying a non-string entry, or a value that is neither list nor
 * string, halts the boot.
 */
final class TrustedProxyKnobs
{
    /**
     * Reads the trusted-proxy list knob's value.
     *
     * @return list<string>
     *
     * @throws HttpException when the value is neither a list of strings
     *                       nor a comma-separated string
     */
    public static function read(mixed $value): array
    {
        if (\is_array($value)) {
            return self::entries($value);
        }

        if (\is_string($value)) {
            return self::commaSeparated($value);
        }

        throw new HttpException(
            'The security.rate_limit trusted_proxies knob must be a list of strings or a'
            . ' comma-separated string, ' . get_debug_type($value) . ' given.',
        );
    }

    /**
     * The native list's entries, each proven a string as it is read.
     *
     * @param array<array-key, mixed> $value
     *
     * @return list<string>
     */
    private static function entries(array $value): array
    {
        $entries = [];

        foreach ($value as $entry) {
            if (!\is_string($entry)) {
                throw new HttpException(
                    'The security.rate_limit trusted_proxies knob must be a list of strings, '
                    . get_debug_type($entry) . ' given.',
                );
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * The comma-separated spelling's entries.
     *
     * @return list<string>
     */
    private static function commaSeparated(string $value): array
    {
        $entries = [];

        foreach (explode(',', $value) as $entry) {
            $entry = mb_trim($entry);

            if ($entry === '') {
                continue;
            }

            $entries[] = $entry;
        }

        return $entries;
    }
}
