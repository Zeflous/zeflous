<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The auth family's principal-field reading: the shared shape of the
 * three string knobs the family reads -- the principal, the scope it
 * grants and the scope it requires -- following the family's own
 * shared-reader lineage ({@see StrictToggles}, {@see StrictIntegers},
 * the rate-limit family's {@see TrustedProxyKnobs}).
 *
 * The reading is strict the way every knob reading is: a non-empty
 * string within the context's own bound, or the boot halts with the
 * knob named -- never a silent default over a misspelled value.
 */
final class AuthFieldKnobs
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /** Every principal field's ceiling, in characters. */
    private const int MAX_CHARS = 128;

    /**
     * Reads one principal-field knob's value: a non-empty string
     * within the bound the authenticated context itself holds.
     *
     * @param string $knob the knob's own name, as the rejection spells it
     */
    public static function read(mixed $value, string $knob): string
    {
        if (\is_string($value) && $value !== '' && mb_strlen($value) <= self::MAX_CHARS) {
            return $value;
        }

        throw new HttpException(
            'The security.auth ' . $knob . ' knob must be a non-empty string of at most '
            . self::MAX_CHARS . ' characters, '
            . (\is_string($value) ? "'" . $value . "'" : get_debug_type($value)) . self::GIVEN_SUFFIX,
        );
    }
}
