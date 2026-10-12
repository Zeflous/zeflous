<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\StreamInterface;

/**
 * The payload copy behind an upload move: the stream is drained in chunks
 * into the target resource, resuming from the byte count the engine
 * actually accepted so a partial write loses data on neither side.
 *
 * Byte lengths and slices answer through the 8bit encoding, which is the
 * byte-exact form -- an upload payload is opaque bytes, not text. The
 * failure message is byte-identical to the reference implementation
 * (legacy `zef-framework` `Adapters.Http.UploadedFile`).
 */
final class UploadedTransfer
{
    private const int COPY_CHUNK_SIZE = 8192;

    /**
     * Copies the payload's remainder into the destination resource.
     *
     * @param resource $dest
     *
     * @throws HttpException when the engine write fails or accepts nothing
     */
    public static function copy(StreamInterface $stream, mixed $dest): void
    {
        while (!$stream->eof()) {
            $chunk = $stream->read(self::COPY_CHUNK_SIZE);
            if ($chunk === '') {
                break;
            }

            $offset = 0;
            $length = mb_strlen($chunk, '8bit');
            while ($offset < $length) {
                $written = fwrite($dest, mb_substr($chunk, $offset, $length - $offset, '8bit'));
                if ($written === false || $written === 0) {
                    throw new HttpException('Unable to write uploaded file.');
                }

                $offset += $written;
            }
        }
    }
}
