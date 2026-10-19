<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * A scriptable stream-wrapper double for the replay-snapshot writer's
 * whole file protocol: tests register it under its own scheme, seed the
 * short-write schedule / lock answer it must give, and read back both
 * the bytes it captured and the ordered protocol log (open, chmod
 * metadata, lock, writes, unlock, close, unlink) -- the complete set of
 * engine calls the writer makes against a real filesystem, observed
 * deterministically instead of by racing a disk.
 *
 * The write schedule is a list of per-call byte budgets consumed in
 * order (the last entry repeats once the list is exhausted, and an
 * empty schedule writes everything): a budget of zero simulates the
 * engine's zero-byte fwrite, a budget below the string length simulates
 * a short write that splits mid-character, and the double appends only
 * the budgeted prefix while reporting the budget as the write count --
 * exactly the contract fwrite keeps on a full pipe.
 *
 * @internal
 */
final class ReplayStreamWrapper
{
    public const string SCHEME = 'zefreplay';

    /** @var null|resource */
    public $context;

    /** @var array<string, string> the captured bytes, keyed by full URL */
    public static array $contents = [];

    /** @var list<string> the ordered protocol call log */
    public static array $calls = [];

    /** @var list<int> the per-call write byte budgets, consumed in order */
    public static array $writeLimits = [];

    /** Whether the next flock answer grants or refuses the lock. */
    public static bool $lockGranted = true;

    private static int $writeCursor = 0;

    private string $path = '';

    /** The full URL the writer will fopen for one snapshot id. */
    public static function urlFor(string $id): string
    {
        return self::SCHEME . '://replays/' . $id . '.json';
    }

    public static function reset(): void
    {
        self::$contents = [];
        self::$calls = [];
        self::$writeLimits = [];
        self::$lockGranted = true;
        self::$writeCursor = 0;
    }

    public static function register(): void
    {
        if (\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            return;
        }

        stream_wrapper_register(self::SCHEME, self::class);
    }

    public static function unregister(): void
    {
        if (!\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            return;
        }

        stream_wrapper_unregister(self::SCHEME);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->path = $path;
        self::$calls[] = 'open:' . $mode;

        return true;
    }

    public function stream_write(string $data): int
    {
        $limit = self::$writeLimits[self::$writeCursor] ?? self::byteLength($data);
        ++self::$writeCursor;
        self::$contents[$this->path] = (self::$contents[$this->path] ?? '') . self::bytePrefix($data, $limit);
        self::$calls[] = 'write:' . $limit;

        return $limit;
    }

    public function stream_lock(int $operation): bool
    {
        self::$calls[] = 'flock:' . $operation;

        return self::$lockGranted;
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        self::$calls[] = 'metadata:' . $option;

        return true;
    }

    /**
     * @return array<int|string, int>
     */
    public function stream_stat(): array
    {
        return ['size' => self::byteLength(self::$contents[$this->path] ?? '')];
    }

    public function url_stat(string $path, int $flags): false
    {
        return false;
    }

    public function unlink(string $path): bool
    {
        self::$calls[] = 'unlink';

        return true;
    }

    public function stream_close(): void
    {
        self::$calls[] = 'close';
    }

    /**
     * The engine's fwrite arithmetic is byte-wise, and the coding
     * standard's multibyte-safe fixer would silently turn any
     * `strlen()` call here into character arithmetic -- the same trap
     * the URI encoder's own header documents -- so the byte count is
     * gathered through `isset()` offsets instead.
     */
    private static function byteLength(string $data): int
    {
        $length = 0;
        while (isset($data[$length])) {
            ++$length;
        }

        return $length;
    }

    /** The budgeted byte prefix, through the same offset arithmetic. */
    private static function bytePrefix(string $data, int $limit): string
    {
        $prefix = '';
        for ($offset = 0; $offset < $limit && isset($data[$offset]); ++$offset) {
            $prefix .= $data[$offset];
        }

        return $prefix;
    }
}
