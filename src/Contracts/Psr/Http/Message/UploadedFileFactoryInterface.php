<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Factory for uploaded file objects, as defined by PSR-17.
 *
 * Vendored by the ZEF zero-library shim.
 */
interface UploadedFileFactoryInterface
{
    /**
     * Creates a new uploaded file.
     *
     * @param StreamInterface $stream The underlying stream.
     * @param int|null $size The size in bytes, or null to derive it from the
     *        stream.
     * @param int $error The PHP upload error code (UPLOAD_ERR_XXX).
     * @param string|null $clientFilename The client-side filename.
     * @param string|null $clientMediaType The client-side media type.
     */
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ): UploadedFileInterface;
}
