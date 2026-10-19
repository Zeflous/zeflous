<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use RuntimeException;

/** Performs locked, permission-restricted JSONL appends with rollback on partial writes. */
final class JsonLineWriter
{
    public static function append(string $path, string $line): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Refusing to write through a symlink log destination.');
        }

        $stream = fopen($path, 'ab');
        if ($stream === false) {
            throw new RuntimeException('Unable to open the configured JSON log destination.');
        }

        $locked = false;

        try {
            if (!flock($stream, \LOCK_EX)) {
                throw new RuntimeException('Unable to acquire the JSON log write lock.');
            }
            $locked = true;

            $startOffset = self::secureFile($stream, $path);
            self::writeRecord($stream, $line, $startOffset);
        } finally {
            if ($locked) {
                flock($stream, \LOCK_UN);
            }
            fclose($stream);
        }
    }

    /** @param resource $stream */
    private static function secureFile($stream, string $path): int
    {
        $opened = fstat($stream);
        $current = lstat($path);
        if (
            !\is_array($opened)
            || !\is_array($current)
            || is_link($path)
            || $opened['dev'] !== $current['dev']
            || $opened['ino'] !== $current['ino']
        ) {
            throw new RuntimeException('The JSON log destination changed while it was being opened.');
        }

        if (!chmod($path, 0o600)) {
            throw new RuntimeException('Unable to secure the JSON log destination permissions.');
        }

        return max(0, $opened['size']);
    }

    /** @param resource $stream */
    private static function writeRecord($stream, string $line, int $startOffset): void
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
