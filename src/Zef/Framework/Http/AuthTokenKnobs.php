<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The auth family's token-table knob reading: the two spellings one
 * list carries -- a native list of strings (the file's declaration)
 * or the comma-separated string an environment overlay writes
 * (`APP_SECURITY__AUTH__TOKENS=first,second`), trimmed with empty
 * entries dropped (a trailing separator is cosmetic, not an entry) --
 * following the rate-limit family's own {@see TrustedProxyKnobs}
 * lineage, extracted for the auth family's bulk knob.
 *
 * The shape of each entry (the token68 grammar, the entropy floor,
 * the ceiling) is the bounds' own ({@see AuthBounds::assertTokenShape()}),
 * asserted by the policy once the table is read.
 */
final class AuthTokenKnobs
{
    /**
     * Reads the static token table knob's value.
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
            'The security.auth tokens knob must be a list of strings or a comma-separated'
            . ' string, ' . get_debug_type($value) . ' given.',
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
                    'The security.auth tokens knob must be a list of strings, '
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
