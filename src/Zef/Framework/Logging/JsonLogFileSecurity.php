<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

/** Verifies the opened log file is the file on disk and restricts it to owner-only permissions. */
final class JsonLogFileSecurity
{
    /**
     * @param resource $stream
     *
     * @return int the byte offset a partial record must roll back to
     */
    public static function secure($stream, string $path): int
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
            throw new JsonLogWriteException('The JSON log destination changed while it was being opened.');
        }

        if (!chmod($path, 0o600)) {
            throw new JsonLogWriteException('Unable to secure the JSON log destination permissions.');
        }

        return max(0, $opened['size']);
    }
}
