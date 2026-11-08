<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * A recording stream wrapper the clear verb's lock-path tests ride: a
 * plain in-memory "file" whose every stream operation is recorded, and
 * whose lock answer the test arms -- so the verb's flush / unlock /
 * close discipline and its lock refusal are observed as data, never by
 * re-deriving what the engine did.
 *
 * Registered per test under the `zeflock://` and `zefstat://` schemes and torn down after
 * (the registration is process-global, exactly like the engine's own
 * wrappers).
 *
 * @internal
 */
final class ZefLockStreamWrapper
{
    /**
     * The stream context the engine hands every wrapper instance -- a
     * declared property because the engine assigns it dynamically.
     */
    public mixed $context = null;

    public static bool $lockAnswer = true;

    public static bool $openAnswer = true;

    public static int $statMode = 0o100666;

    /** @var list<string> the recorded operations, in call order */
    public static array $calls = [];

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$calls[] = 'open:' . $mode;

        return self::$openAnswer;
    }

    public function stream_lock(int $operation): bool
    {
        self::$calls[] = 'lock:' . $operation;

        return self::$lockAnswer;
    }

    public function stream_truncate(int $newSize): bool
    {
        self::$calls[] = 'truncate:' . $newSize;

        return true;
    }

    public function stream_flush(): bool
    {
        self::$calls[] = 'flush';

        return true;
    }

    public function stream_close(): void
    {
        self::$calls[] = 'close';
    }

    /**
     * The wrapper's own stat answer: a real, writable, owner-read
     * regular file, so the verb's is_file/is_writable posture holds.
     *
     * @return array<string, int>
     */
    public function url_stat(): array
    {
        $uid = getmyuid();
        $gid = getmygid();

        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => self::$statMode,
            'nlink' => 1,
            'uid' => $uid === false ? 0 : $uid,
            'gid' => $gid === false ? 0 : $gid,
            'rdev' => 0,
            'size' => 0,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => 0,
            'blocks' => 0,
        ];
    }
}
