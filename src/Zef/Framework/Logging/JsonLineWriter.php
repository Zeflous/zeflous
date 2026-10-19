<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

/** Performs locked JSONL appends through the secured-destination and bounded-record collaborators. */
final class JsonLineWriter
{
    public static function append(string $path, string $line): void
    {
        if (is_link($path)) {
            throw new JsonLogWriteException('Refusing to write through a symlink log destination.');
        }

        $stream = fopen($path, 'ab');
        if ($stream === false) {
            throw new JsonLogWriteException('Unable to open the configured JSON log destination.');
        }

        $locked = false;

        try {
            if (!flock($stream, \LOCK_EX)) {
                throw new JsonLogWriteException('Unable to acquire the JSON log write lock.');
            }

            $locked = true;

            $startOffset = JsonLogFileSecurity::secure($stream, $path);
            JsonLineRecordWriter::write($stream, $line, $startOffset);
        } finally {
            if ($locked) {
                flock($stream, \LOCK_UN);
            }

            fclose($stream);
        }
    }
}
