<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use RuntimeException;

/** Writes one complete JSONL record, rolling the file back when a write cannot finish. */
final class JsonLineRecordWriter
{
    /**
     * @param resource $stream
     */
    public static function write($stream, string $line, int $startOffset): void
    {
        $offset = 0;
        $length = mb_strlen($line);
        while ($offset < $length) {
            $written = fwrite($stream, mb_substr($line, $offset));
            if ($written === false || $written === 0) {
                if (!ftruncate($stream, max(0, $startOffset))) {
                    throw new RuntimeException('Unable to roll back an incomplete JSON log record.');
                }

                throw new RuntimeException('Unable to write a complete JSON log record.');
            }

            $offset += $written;
        }
    }
}
