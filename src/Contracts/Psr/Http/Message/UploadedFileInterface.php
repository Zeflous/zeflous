<?php

declare(strict_types=1);

namespace Psr\Http\Message;

/**
 * Value object representing a file uploaded through an HTTP request, as
 * defined by PSR-7.
 *
 * Vendored by the ZEF zero-library shim. Instances are immutable: moving the
 * file to its final destination is a one-shot operation that closes the
 * underlying stream.
 */
interface UploadedFileInterface
{
    /**
     * Retrieves a stream representing the uploaded file.
     *
     * @throws \RuntimeException When no stream is available (upload error or
     *         the file was already moved).
     */
    public function getStream(): StreamInterface;

    /**
     * Moves the uploaded file to a new location.
     *
     * @param non-empty-string $targetPath Path to move the uploaded file to.
     *
     * @throws \RuntimeException On any failure.
     */
    public function moveTo(string $targetPath): void;

    /**
     * Retrieves the file size, when known.
     */
    public function getSize(): ?int;

    /**
     * Retrieves the error associated with the uploaded file.
     *
     * @return int One of the UPLOAD_ERR_XXX constants.
     */
    public function getError(): int;

    /**
     * Retrieves the filename sent by the client, when known.
     *
     * @return string|null The filename, or null when unknown. This must not
     *         be trusted for filesystem operations.
     */
    public function getClientFilename(): ?string;

    /**
     * Retrieves the media type sent by the client, when known.
     *
     * @return string|null The media type, or null when unknown.
     */
    public function getClientMediaType(): ?string;
}
