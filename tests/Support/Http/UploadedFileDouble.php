<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Zef\Framework\Http\Stream;

/**
 * Deterministic uploaded-file double: the upload-tree validation only
 * inspects the interface contract, so the leaf carries fixed client
 * metadata and answers a rewindable in-memory stream. The real
 * `UploadedFile` implementation arrives with its own slice; this double
 * exists so the tree tests do not depend on it. Its return types narrow
 * the contract's nullables because the fixed metadata is always known.
 */
final class UploadedFileDouble implements UploadedFileInterface
{
    #[Override]
    public function getStream(): StreamInterface
    {
        return Stream::fromString('uploaded');
    }

    #[Override]
    public function moveTo(string $targetPath): void
    {
        // The double never owns a real upload; the tree guard only checks
        // the interface contract, so there is nothing to move.
    }

    #[Override]
    public function getSize(): int
    {
        return 8;
    }

    #[Override]
    public function getError(): int
    {
        return \UPLOAD_ERR_OK;
    }

    #[Override]
    public function getClientFilename(): string
    {
        return 'upload.txt';
    }

    #[Override]
    public function getClientMediaType(): string
    {
        return 'text/plain';
    }
}
