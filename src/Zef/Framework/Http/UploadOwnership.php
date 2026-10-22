<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The engine's own upload claim, leaf by leaf: a file the SAPI
 * actually accepted through a request body answers
 * {@see is_uploaded_file()} true, and an error-OK entry whose
 * temporary file the engine never wrote is the ghost upload the leaf
 * builder already downgrades ({@see UploadLeaf} -- a body PHP never
 * actually wrote reads as `NO_FILE`).
 *
 * Split from {@see UploadOrigin} so the walk that anchors a whole bag
 * and the verdict one leaf earns stay archeology-sized units of their
 * own: the verdict is the same doctrine the leaf builder applies at
 * its own seam, expressed once for the raw bag before it enters the
 * source-agnostic normalisation.
 */
final readonly class UploadOwnership
{
    /**
     * One leaf's error axis after the ownership verdict: the disowned
     * error-OK entry (a temporary file the engine never wrote)
     * downgrades to `NO_FILE`, every other verdict passes untouched.
     *
     * @param mixed $temporaryPath the leaf's temporary-file axis value
     * @param mixed $leafError     the leaf's error axis value
     */
    public static function leafError(mixed $temporaryPath, mixed $leafError): mixed
    {
        if (
            $leafError === \UPLOAD_ERR_OK
            && \is_string($temporaryPath)
            && $temporaryPath !== ''
            && !is_uploaded_file($temporaryPath)
        ) {
            return \UPLOAD_ERR_NO_FILE;
        }

        return $leafError;
    }
}
