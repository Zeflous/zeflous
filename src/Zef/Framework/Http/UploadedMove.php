<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\StreamInterface;

/**
 * The one-shot upload move orchestration: the reference implementation's
 * tmp+rename dance.
 *
 * The payload is copied into a temporary sibling whose name the move
 * generated itself, flushed, closed and renamed onto the final path -- a
 * half-written destination can never appear under the caller's name. The
 * collaborators each own one step: {@see UploadedTarget} the target file's
 * lifecycle, {@see UploadedTransfer} the payload copy and
 * {@see UploadedPosition} the read position. Every failure path removes
 * the temporary sibling and restores the read position before the
 * original failure propagates, and a success ends the payload stream's
 * life -- the upload cannot be moved twice.
 */
final class UploadedMove
{
    /**
     * Moves the payload stream onto the target path.
     *
     * @param non-empty-string $targetPath
     *
     * @throws HttpException when the destination directory does not exist
     *                       or any step of the dance fails
     */
    public static function onto(StreamInterface $stream, string $targetPath): void
    {
        UploadedTarget::assertDestinationDirectory($targetPath);
        $tmpTarget = $targetPath . '.zef-tmp-' . bin2hex(random_bytes(8));
        $dest = UploadedTarget::open($tmpTarget);
        $success = false;
        $originalPosition = null;

        try {
            $originalPosition = UploadedPosition::capture($stream);
            UploadedTransfer::copy($stream, $dest);
            fflush($dest);
            fclose($dest);
            $dest = null;
            UploadedTarget::finalize($tmpTarget, $targetPath);
            $success = true;
        } finally {
            if (\is_resource($dest)) {
                fclose($dest);
            }

            if (!$success) {
                UploadedTarget::cleanup($tmpTarget);
                if ($originalPosition !== null) {
                    UploadedPosition::restore($stream, $originalPosition);
                }
            }
        }

        $stream->close();
    }
}
