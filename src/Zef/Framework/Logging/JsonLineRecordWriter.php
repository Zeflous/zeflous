<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

/**
 * Writes one complete JSONL record, rolling the file back when a write
 * cannot finish.
 *
 * The loop is byte-wise, the only unit `fwrite()` itself answers: the
 * cursor advances by the BYTES the stream actually accepted, and the
 * remainder the next call ships is the byte-tail from that exact offset
 * (`unpack()`'s own offset, the byte-presence idiom the boundary
 * already documents -- deliberately not `substr()` arithmetic, which
 * the coding standard's multibyte-safe fixer would silently turn into
 * character arithmetic). A short write on a multibyte line therefore
 * resumes at the line's NEXT BYTE, never at its next character: the
 * character-offset walk this loop once kept skipped the trailing half
 * of a partially-written multibyte sequence and exited "successfully"
 * on a torn record.
 */
final class JsonLineRecordWriter
{
    /**
     * @param resource $stream
     */
    public static function write($stream, string $line, int $startOffset): void
    {
        $offset = 0;

        while (isset($line[$offset])) {
            /** @var null|string $remainder the unpacked byte-tail from the offset */
            $remainder = unpack('a*', $line, $offset)[1] ?? null;

            $written = fwrite($stream, $remainder ?? '');

            if ($written === false || $written === 0) {
                if (!ftruncate($stream, max(0, $startOffset))) {
                    throw new JsonLogWriteException('Unable to roll back an incomplete JSON log record.');
                }

                throw new JsonLogWriteException('Unable to write a complete JSON log record.');
            }

            $offset += $written;
        }
    }
}
