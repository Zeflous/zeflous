<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Http\ZefLockStreamWrapper;
use Zef\Test\Support\Tooling\ErrorsReport;

/**
 * The `errors:report` verb as a pure outcome: every branch of its
 * grammar -- the honest empty answer, the counted report with its exact
 * display, the request-id filter, the one-way path precedence and the
 * three refusal families (layout, usage, symlink).
 *
 * @internal
 */
#[CoversClass(ErrorsReport::class)]
final class ErrorsReportTest extends TestCase
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

    public function testAMissingLogAnswersTheHonestEmptyAnswer(): void
    {
        $path = sys_get_temp_dir() . '/zef-report-absent-' . bin2hex(random_bytes(8)) . '.jsonl';

        $outcome = ErrorsReport::run([$path]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertSame("No readable error log found: {$path}\n", $outcome->stdout);
    }

    public function testAnEmptyLogAnswersZeroRecords(): void
    {
        $path = $this->logFile('');

        try {
            $outcome = ErrorsReport::run([$path]);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame(
                "Error log report: {$path}\nTotal records: 0\n\nBy level:\n\nTop error codes/classes:\n",
                $outcome->stdout,
            );
        } finally {
            unlink($path);
        }
    }

    public function testTheReportCountsLevelsAndCodesWithTheExactDisplay(): void
    {
        $record = static fn (string $level, array $context): string => json_encode(
            ['level' => $level, 'context' => $context],
            \JSON_THROW_ON_ERROR,
        ) . "\n";
        $path = $this->logFile(
            $record('error', ['exception_code' => 'ZEF-DB-42'])
            . $record('error', ['exception_code' => 'ZEF-DB-42'])
            . $record('warning', ['exception_class' => 'RuntimeException']),
        );

        try {
            $outcome = ErrorsReport::run([$path]);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame(
                "Error log report: {$path}\nTotal records: 3\n\nBy level:\n"
                . \sprintf("  %-10s %d\n", 'error', 2)
                . \sprintf("  %-10s %d\n", 'warning', 1)
                . "\nTop error codes/classes:\n"
                . \sprintf("  %-40s %d\n", 'ZEF-DB-42', 2)
                . \sprintf("  %-40s %d\n", 'RuntimeException', 1),
                $outcome->stdout,
            );
        } finally {
            unlink($path);
        }
    }

    public function testEntriesWithoutALevelOrCodeAnswerTheUnknownSpellings(): void
    {
        $path = $this->logFile(
            json_encode(['context' => []], \JSON_THROW_ON_ERROR) . "\n"
            . json_encode(['level' => 'notice', 'context' => []], \JSON_THROW_ON_ERROR) . "\n",
        );

        try {
            $outcome = ErrorsReport::run([$path]);

            self::assertSame(0, $outcome->exitCode);
            self::assertStringContainsString("Total records: 2\n", $outcome->stdout);
            self::assertStringContainsString("By level:\n  unknown    1\n  notice     1\n", $outcome->stdout);
            self::assertStringContainsString(\sprintf("  %-40s %d\n", 'unknown', 2), $outcome->stdout);
        } finally {
            unlink($path);
        }
    }

    public function testNonArrayLinesAreSkippedNotCounted(): void
    {
        $path = $this->logFile(
            "not json at all\n"
            . json_encode(['level' => 'error', 'context' => []], \JSON_THROW_ON_ERROR) . "\n"
            . "42\n",
        );

        try {
            $outcome = ErrorsReport::run([$path]);

            self::assertSame(0, $outcome->exitCode);
            self::assertStringContainsString("Total records: 1\n", $outcome->stdout);
        } finally {
            unlink($path);
        }
    }

    public function testTheRequestIdFilterAnswersOnlyTheMatchingRecords(): void
    {
        $record = static fn (array $context): string => json_encode(
            ['level' => 'error', 'context' => $context],
            \JSON_THROW_ON_ERROR,
        ) . "\n";
        $path = $this->logFile(
            $record(['request_id' => 'req-a'])
            . $record(['request_id' => 'req-b'])
            . $record([]),
        );

        try {
            $outcome = ErrorsReport::run([$path, '--request-id=req-a']);

            self::assertSame(0, $outcome->exitCode);
            self::assertStringContainsString(" (request_id=req-a)\n", $outcome->stdout);
            self::assertStringContainsString("Total records: 1\n", $outcome->stdout);
        } finally {
            unlink($path);
        }
    }

    public function testTheEnvironmentSpellingAnswersWhenNoPositionalPathWasGiven(): void
    {
        $path = $this->logFile('');
        putenv('ZEF_ERROR_LOG=' . $path);

        try {
            $outcome = ErrorsReport::run([]);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame(
                "Error log report: {$path}\nTotal records: 0\n\nBy level:\n\nTop error codes/classes:\n",
                $outcome->stdout,
            );
        } finally {
            unlink($path);
        }
    }

    public function testTheConfirmMarkerIsNeverTakenForThePositionalPath(): void
    {
        $path = $this->logFile('');
        putenv('ZEF_ERROR_LOG=' . $path);

        try {
            $outcome = ErrorsReport::run(['--confirm']);

            self::assertSame(0, $outcome->exitCode);
            self::assertStringContainsString("Error log report: {$path}\n", $outcome->stdout);
        } finally {
            unlink($path);
        }
    }

    public function testAnInvalidRequestIdFilterIsAUsageDefect(): void
    {
        $outcome = ErrorsReport::run(['--request-id=not valid!']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("Invalid request ID filter.\n", $outcome->stderr);
    }

    public function testASymlinkedLogPathIsAUsageDefect(): void
    {
        $target = sys_get_temp_dir() . '/zef-report-target-' . bin2hex(random_bytes(8)) . '.jsonl';
        $link = sys_get_temp_dir() . '/zef-report-link-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents($target, '');
        symlink($target, $link);

        try {
            $outcome = ErrorsReport::run([$link]);

            self::assertSame(2, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertSame("Refusing to follow a symlink log path.\n", $outcome->stderr);
        } finally {
            unlink($link);
            unlink($target);
        }
    }

    public function testAMisprovisionedLayoutNamesItsOwnWords(): void
    {
        putenv('ZEF_STORAGE_ROOT=' . sys_get_temp_dir() . '/zef-report-absent-' . bin2hex(random_bytes(8)));

        $outcome = ErrorsReport::run([]);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            'The error log storage layout refused: '
            . "The storage root must be an existing writable non-symlink directory.\n",
            $outcome->stderr,
        );
    }

    public function testAnUnreadableLogIsAnOperationalRefusal(): void
    {
        $path = $this->logFile('');
        chmod($path, 0o000);

        try {
            $outcome = ErrorsReport::run([$path]);

            self::assertSame(0, $outcome->exitCode);
            self::assertSame("No readable error log found: {$path}\n", $outcome->stdout);
        } finally {
            chmod($path, 0o644);
            unlink($path);
        }
    }

    public function testTheCountsAndCodesRenderInDescendingOrder(): void
    {
        $record = static fn (array $context): string => json_encode(
            ['level' => $context['level'], 'context' => $context],
            \JSON_THROW_ON_ERROR,
        ) . "\n";
        $path = $this->logFile(
            $record(['level' => 'notice', 'exception_code' => 'ZEF-A'])
            . $record(['level' => 'error', 'exception_code' => 'ZEF-B'])
            . $record(['level' => 'error', 'exception_code' => 'ZEF-B'])
        );

        try {
            $outcome = ErrorsReport::run([$path]);

            self::assertSame(0, $outcome->exitCode);
            self::assertStringContainsString(
                "By level:\n"
                . \sprintf("  %-10s %d\n", 'error', 2)
                . \sprintf("  %-10s %d\n", 'notice', 1)
                . "\nTop error codes/classes:\n"
                . \sprintf("  %-40s %d\n", 'ZEF-B', 2)
                . \sprintf("  %-40s %d\n", 'ZEF-A', 1),
                $outcome->stdout,
            );
        } finally {
            unlink($path);
        }
    }

    public function testOnlyTheTopTenCodesRide(): void
    {
        $lines = '';
        $expectedRows = '';
        foreach (range(1, 11) as $index) {
            $lines .= json_encode(
                ['level' => 'error', 'context' => ['exception_code' => "ZEF-CODE-{$index}"]],
                \JSON_THROW_ON_ERROR,
            ) . "\n";
        }

        foreach (range(1, 10) as $index) {
            $expectedRows .= \sprintf("  %-40s %d\n", "ZEF-CODE-{$index}", 1);
        }

        $path = $this->logFile($lines);

        try {
            $outcome = ErrorsReport::run([$path]);

            self::assertSame(0, $outcome->exitCode);
            self::assertStringEndsWith(
                "\nTop error codes/classes:\n" . $expectedRows,
                $outcome->stdout,
            );
            self::assertStringNotContainsString('ZEF-CODE-11', $outcome->stdout);
        } finally {
            unlink($path);
        }
    }

    public function testTheFilterSkipsNonMatchingRecordsBeforeMatchingOnes(): void
    {
        $record = static fn (array $context): string => json_encode(
            ['level' => 'error', 'context' => $context],
            \JSON_THROW_ON_ERROR,
        ) . "\n";
        $path = $this->logFile(
            $record(['request_id' => 'req-other'])
            . $record(['request_id' => 'req-a'])
        );

        try {
            $outcome = ErrorsReport::run([$path, '--request-id=req-a']);

            self::assertSame(0, $outcome->exitCode);
            self::assertStringContainsString("Total records: 1\n", $outcome->stdout);
        } finally {
            unlink($path);
        }
    }

    public function testAStreamStatUnreadableLogIsTheHonestEmptyAnswer(): void
    {
        ZefLockStreamWrapper::$calls = [];
        ZefLockStreamWrapper::$lockAnswer = true;
        ZefLockStreamWrapper::$openAnswer = false;
        ZefLockStreamWrapper::$statMode = 0o100000;
        stream_wrapper_register('zefstat', ZefLockStreamWrapper::class);

        try {
            // The engine's own warning about the unopenable stream is the
            // environment's answer, not the verb's.
            set_error_handler(static fn (): bool => true);

            try {
                $outcome = ErrorsReport::run(['zefstat://log']);
            } finally {
                restore_error_handler();
            }

            self::assertSame(0, $outcome->exitCode);
            self::assertSame("No readable error log found: zefstat://log\n", $outcome->stdout);
            self::assertSame('', $outcome->stderr);
        } finally {
            stream_wrapper_unregister('zefstat');
            ZefLockStreamWrapper::$openAnswer = true;
            ZefLockStreamWrapper::$statMode = 0o100666;
        }
    }

    /**
     * A log file carrying the given contents.
     *
     * @return string the path
     */
    private function logFile(string $contents): string
    {
        $path = sys_get_temp_dir() . '/zef-report-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents($path, $contents);

        return $path;
    }
}
