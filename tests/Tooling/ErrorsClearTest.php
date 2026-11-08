<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Http\ZefLockStreamWrapper;
use Zef\Test\Support\Tooling\ErrorsClear;

/**
 * The `errors:clear` verb as a pure outcome: the `--confirm` refusal the
 * destructive verb owns, the truncation under the exclusive lock, the
 * one-way path precedence riding the shared folds, and the operational
 * refusals (a missing or unwritable log, a misprovisioned layout).
 *
 * @internal
 */
#[CoversClass(ErrorsClear::class)]
final class ErrorsClearTest extends TestCase
{
    /**
     * Backs up the two environment spellings the fold reads.
     */
    private string $previousErrorLog;

    private string $previousStorageRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->previousErrorLog = (string) getenv('ZEF_ERROR_LOG');
        $this->previousStorageRoot = (string) getenv('ZEF_STORAGE_ROOT');
        putenv('ZEF_ERROR_LOG=');
        putenv('ZEF_STORAGE_ROOT=');
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv('ZEF_ERROR_LOG=' . $this->previousErrorLog);
        putenv('ZEF_STORAGE_ROOT=' . $this->previousStorageRoot);
    }

    public function testTheVerbRefusesWithoutTheConfirmMarker(): void
    {
        $path = $this->logFile("{}\n");

        try {
            $outcome = ErrorsClear::run([$path]);

            self::assertSame(2, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertSame("Refusing destructive operation without --confirm.\n", $outcome->stderr);
            self::assertSame("{}\n", file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function testTheVerbClearsTheLogUnderTheConfirmMarker(): void
    {
        $path = $this->logFile(
            json_encode(['level' => 'error', 'context' => []], \JSON_THROW_ON_ERROR) . "\n",
        );

        try {
            $outcome = ErrorsClear::run([$path, '--confirm']);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', $outcome->stderr);
            self::assertSame("Cleared local JSONL error log: {$path}\n", $outcome->stdout);
            self::assertSame('', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function testTheConfirmMarkerRidesBeforeThePathToo(): void
    {
        $path = $this->logFile("{}\n");

        try {
            $outcome = ErrorsClear::run(['--confirm', $path]);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function testAMissingLogIsAnOperationalRefusal(): void
    {
        $path = sys_get_temp_dir() . '/zef-clear-absent-' . bin2hex(random_bytes(8)) . '.jsonl';

        $outcome = ErrorsClear::run([$path, '--confirm']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("Error log does not exist or is not writable: {$path}\n", $outcome->stderr);
    }

    public function testAnUnwritableLogIsAnOperationalRefusal(): void
    {
        $path = $this->logFile("{}\n");
        chmod($path, 0o444);

        try {
            $outcome = ErrorsClear::run([$path, '--confirm']);

            self::assertSame(1, $outcome->exitCode);
            self::assertSame("Error log does not exist or is not writable: {$path}\n", $outcome->stderr);
        } finally {
            chmod($path, 0o644);
            unlink($path);
        }
    }

    public function testTheEnvironmentSpellingAnswersWhenNoPositionalPathWasGiven(): void
    {
        $path = $this->logFile("{}\n");
        putenv('ZEF_ERROR_LOG=' . $path);

        try {
            $outcome = ErrorsClear::run(['--confirm']);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame('', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function testAnInvalidRequestIdFilterIsAUsageDefectEvenForTheClearVerb(): void
    {
        $outcome = ErrorsClear::run(['--request-id=not valid!', '--confirm']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame("Invalid request ID filter.\n", $outcome->stderr);
    }

    public function testASymlinkedLogPathIsAUsageDefect(): void
    {
        $target = sys_get_temp_dir() . '/zef-clear-target-' . bin2hex(random_bytes(8)) . '.jsonl';
        $link = sys_get_temp_dir() . '/zef-clear-link-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents($target, '');
        symlink($target, $link);

        try {
            $outcome = ErrorsClear::run([$link, '--confirm']);

            self::assertSame(2, $outcome->exitCode);
            self::assertSame("Refusing to follow a symlink log path.\n", $outcome->stderr);
        } finally {
            unlink($link);
            unlink($target);
        }
    }

    public function testAMisprovisionedLayoutNamesItsOwnWords(): void
    {
        putenv('ZEF_STORAGE_ROOT=' . sys_get_temp_dir() . '/zef-clear-absent-' . bin2hex(random_bytes(8)));

        $outcome = ErrorsClear::run(['--confirm']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            'The error log storage layout refused: '
            . "The storage root must be an existing writable non-symlink directory.\n",
            $outcome->stderr,
        );
    }

    public function testTheClearFlushesUnlocksAndClosesUnderTheLock(): void
    {
        self::registerLockStream();

        try {
            $outcome = ErrorsClear::run(['zeflock://log', '--confirm']);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame("Cleared local JSONL error log: zeflock://log\n", $outcome->stdout);
            self::assertContains('truncate:0', ZefLockStreamWrapper::$calls);
            self::assertContains('flush', ZefLockStreamWrapper::$calls);
            self::assertContains('lock:' . \LOCK_UN, ZefLockStreamWrapper::$calls);
            self::assertContains('close', ZefLockStreamWrapper::$calls);
            self::assertContains('lock:' . \LOCK_EX, ZefLockStreamWrapper::$calls);
            self::assertGreaterThan(
                array_search('lock:' . \LOCK_EX, ZefLockStreamWrapper::$calls, true),
                array_search('lock:' . \LOCK_UN, ZefLockStreamWrapper::$calls, true),
            );
        } finally {
            self::unregisterLockStream();
        }
    }

    public function testALockTheEnvironmentRefusesIsAnOperationalRefusal(): void
    {
        self::registerLockStream();
        ZefLockStreamWrapper::$lockAnswer = false;

        try {
            $outcome = ErrorsClear::run(['zeflock://log', '--confirm']);

            self::assertSame(1, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertSame("Unable to lock error log for clearing.\n", $outcome->stderr);
            self::assertContains('close', ZefLockStreamWrapper::$calls);
            self::assertNotContains('truncate:0', ZefLockStreamWrapper::$calls);
        } finally {
            self::unregisterLockStream();
        }
    }

    public function testAStreamTheEngineCannotOpenIsAnOperationalRefusal(): void
    {
        self::registerLockStream();
        ZefLockStreamWrapper::$openAnswer = false;

        try {
            // The engine's own warning about the unopenable stream is the
            // environment's answer, not the verb's.
            set_error_handler(static fn (): bool => true);

            try {
                $outcome = ErrorsClear::run(['zeflock://log', '--confirm']);
            } finally {
                restore_error_handler();
            }

            self::assertSame(1, $outcome->exitCode);
            self::assertSame("Unable to lock error log for clearing.\n", $outcome->stderr);
            self::assertNotContains('close', ZefLockStreamWrapper::$calls);
            self::assertNotContains('truncate:0', ZefLockStreamWrapper::$calls);
        } finally {
            self::unregisterLockStream();
        }
    }

    /**
     * A log file carrying the given contents.
     *
     * @return string the path
     */
    private function logFile(string $contents): string
    {
        $path = sys_get_temp_dir() . '/zef-clear-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Registers the recording lock stream under its own scheme.
     */
    private function registerLockStream(): void
    {
        ZefLockStreamWrapper::$calls = [];
        ZefLockStreamWrapper::$lockAnswer = true;
        ZefLockStreamWrapper::$openAnswer = true;
        ZefLockStreamWrapper::$statMode = 0o100666;
        stream_wrapper_register('zeflock', ZefLockStreamWrapper::class);
    }

    /**
     * Tears the recording lock stream's registration down.
     */
    private function unregisterLockStream(): void
    {
        stream_wrapper_unregister('zeflock');
        ZefLockStreamWrapper::$calls = [];
        ZefLockStreamWrapper::$lockAnswer = true;
        ZefLockStreamWrapper::$openAnswer = true;
        ZefLockStreamWrapper::$statMode = 0o100666;
    }
}
