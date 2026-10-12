<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Builds one uploaded-file leaf from its raw specification values.
 *
 * The reference implementation's guards are preserved move-verbatim: a
 * "ghost upload" (error OK with no tmp_name -- a body PHP never actually
 * wrote) is downgraded to `NO_FILE` so `getError()` tells the truth
 * instead of trusting the client-declared size, and only a clean upload
 * with a real temporary file gets a stream over that file; every other
 * leaf carries the empty stream, keeping the one-shot move semantics
 * honest. The axis coercions (error code, client-declared size) live in
 * {@see UploadCoercion}.
 */
final class UploadLeaf
{
    /**
     * Builds the leaf one specification node describes.
     *
     * @param mixed $name  the client filename, null when not a string
     * @param mixed $type  the client media type, null when not a string
     * @param mixed $tmp   the temporary file path, empty when absent
     * @param mixed $error the SAPI upload error code
     * @param mixed $size  the client-declared size in bytes
     */
    public static function build(mixed $name, mixed $type, mixed $tmp, mixed $error, mixed $size): UploadedFile
    {
        $errorCode = UploadCoercion::errorCode($error);
        $temporaryPath = \is_string($tmp) ? $tmp : '';
        $hasPayload = $temporaryPath !== '';

        if (!$hasPayload && $errorCode === \UPLOAD_ERR_OK) {
            $errorCode = \UPLOAD_ERR_NO_FILE;
        }

        $stream = $hasPayload && $errorCode === \UPLOAD_ERR_OK
            ? new Stream(StreamOpener::open($temporaryPath, 'rb'))
            : Stream::fromString('');

        return new UploadedFile(
            $stream,
            UploadCoercion::size($size),
            $errorCode,
            \is_string($name) ? $name : null,
            \is_string($type) ? $type : null,
        );
    }
}
