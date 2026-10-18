<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use RuntimeException;
use Throwable;

/** Writes one exclusive, owner-only replay snapshot and removes partial files on failure. */
final class ReplaySnapshotWriter
{
    public static function write(string $directory, string $id, string $json): void
    {
        $destination = $directory . DIRECTORY_SEPARATOR . $id . '.json';
        $stream = fopen($destination, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Unable to create an exclusive replay snapshot.');
        }

        $locked = false;
        try {
            if (!chmod($destination, 0600)) {
                throw new RuntimeException('Unable to secure the replay snapshot file.');
            }

            if (!flock($stream, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the replay snapshot file.');
            }
            $locked = true;

            self::writeAll($stream, $json . "\n");
        } catch (Throwable $throwable) {
            if ($locked) {
                flock($stream, LOCK_UN);
            }
            fclose($stream);

            if (!unlink($destination)) {
                throw new RuntimeException('Unable to remove an incomplete replay snapshot.', 0, $throwable);
            }

            throw $throwable;
        }

        if ($locked) {
            flock($stream, LOCK_UN);
        }
        fclose($stream);
    }

    /** @param resource $stream */
    private static function writeAll($stream, string $contents): void
    {
        $offset = 0;
        $length = strlen($contents);
        while ($offset < $length) {
            $written = fwrite($stream, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write a complete replay snapshot.');
            }

            $offset += $written;
        }
    }
}
