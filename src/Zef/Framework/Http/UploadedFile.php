<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * The zero-library PSR-7 uploaded file: client metadata around a one-shot
 * move onto the filesystem.
 *
 * The move delegates to {@see UploadedMove} -- the tmp+rename dance over
 * {@see UploadedTarget} (the target file lifecycle), {@see UploadedTransfer}
 * (the payload copy), {@see UploadedPosition} (the read position) and
 * {@see ScopedDiagnostics} (the capture-style engine seam) -- the same
 * depth-split the stream engine uses to stay under the architecture gate's
 * difficulty budget. A move is one-shot: after it succeeds the stream is
 * closed and every further move or stream access fails closed. Every
 * failure message is byte-identical to the reference implementation
 * (legacy `zef-framework` `Adapters.Http.UploadedFile`) under the layer's
 * single exception root.
 *
 * @api
 */
final class UploadedFile implements UploadedFileInterface
{
    private bool $moved = false;

    /**
     * @param StreamInterface $stream          the uploaded payload's stream
     * @param null|int        $size            the size in bytes, when known
     * @param int             $error           one of the UPLOAD_ERR_XXX constants
     * @param null|string     $clientFilename  the filename the client sent
     * @param null|string     $clientMediaType the media type the client sent
     *
     * @throws HttpException when the size is negative
     */
    public function __construct(
        private readonly StreamInterface $stream,
        private readonly ?int $size = null,
        private readonly int $error = \UPLOAD_ERR_OK,
        private readonly ?string $clientFilename = null,
        private readonly ?string $clientMediaType = null,
    ) {
        // Mirror of the reference factory's guard: a negative size is
        // meaningless (PSR-7 section 6.5) and must not be stored verbatim.
        if ($size !== null && $size < 0) {
            throw new HttpException('Uploaded file size must not be negative.');
        }
    }

    /**
     * @throws HttpException when the upload carries an error code or was
     *                       already moved
     */
    #[Override]
    public function getStream(): StreamInterface
    {
        if ($this->error !== \UPLOAD_ERR_OK) {
            throw new HttpException("Uploaded file is not available (error {$this->error}).");
        }

        $this->assertNotMoved();

        return $this->stream;
    }

    /**
     * @param string $targetPath the destination path
     *
     * @throws HttpException when the target path is empty, the upload carries
     *                       an error code, the upload was already moved, the
     *                       destination directory does not exist, or any step
     *                       of the tmp+rename dance fails
     */
    #[Override]
    public function moveTo(string $targetPath): void
    {
        if ($targetPath === '') {
            throw new HttpException('Target path must not be empty.');
        }

        if ($this->error !== \UPLOAD_ERR_OK) {
            throw new HttpException("Cannot move uploaded file with error code {$this->error}.");
        }

        $this->assertNotMoved();
        UploadedMove::onto($this->stream, $targetPath);
        $this->moved = true;
    }

    #[Override]
    public function getSize(): ?int
    {
        return $this->size ?? $this->stream->getSize();
    }

    #[Override]
    public function getError(): int
    {
        return $this->error;
    }

    #[Override]
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    #[Override]
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    /**
     * @throws HttpException when the upload was already moved
     */
    private function assertNotMoved(): void
    {
        if ($this->moved) {
            throw new HttpException('Uploaded file has already been moved.');
        }
    }
}
