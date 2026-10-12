<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Coerces the raw SAPI upload axis values to their typed forms.
 *
 * The reference implementation's coercions are preserved move-verbatim:
 * a non-integer error code coerces to `UPLOAD_ERR_NO_FILE` (anything
 * unrecognisable is "no file", never a fabricated success), and the
 * client-declared size coerces from integer or numeric string to
 * integer while anything else answers null (unknown) -- the
 * constructor's negative guard and the stream fallback own the rest.
 */
final class UploadCoercion
{
    /**
     * Coerces a SAPI upload error value to the `UPLOAD_ERR_*` integer it
     * represents; anything unrecognisable answers `NO_FILE`.
     *
     * @param mixed $error the raw error axis value
     */
    public static function errorCode(mixed $error): int
    {
        if (\is_int($error)) {
            return $error;
        }

        if (\is_string($error) && ctype_digit($error)) {
            return (int) $error;
        }

        return \UPLOAD_ERR_NO_FILE;
    }

    /**
     * Coerces the client-declared size to the integer it represents: an
     * integer answers itself, a numeric string answers its value, and
     * anything else answers null (unknown).
     *
     * @param mixed $size the raw size axis value
     */
    public static function size(mixed $size): ?int
    {
        if (\is_int($size)) {
            return $size;
        }

        if (\is_string($size) && is_numeric($size)) {
            return (int) $size;
        }

        return null;
    }
}
