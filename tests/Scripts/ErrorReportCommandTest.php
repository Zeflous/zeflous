<?php

declare(strict_types=1);

namespace Zef\Test\Scripts;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Exercises the error report command end to end as a real subprocess, the
 * same way an operator's shell invokes it -- the errors lane's own CLI
 * family discipline (`bin/zef errors:*`): honest exit codes (0 the answer
 * or the empty log, 1 the unreadable log) and a display that carries no
 * control byte a stored record could smuggle into the terminal.
 *
 * @internal
 */
#[CoversNothing]
final class ErrorReportCommandTest extends TestCase
{
    private const string COMMAND = 'bin/zef';

    public function testControlBytesNeverReachTheReportDisplay(): void
    {
        $path = sys_get_temp_dir() . '/zef-report-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents($path, json_encode([
            'level' => "er\x1b[2Jror",
            'context' => ['exception_code' => "a\r\nb\x1b]0;pwned\x07"],
        ], \JSON_THROW_ON_ERROR) . "\n");

        try {
            [$exitCode, $output] = $this->runCommand(['errors:report', $path]);

            self::assertSame(0, $exitCode);
            self::assertStringNotContainsString("\x1b", $output);
            self::assertStringNotContainsString("\r", $output);
            self::assertStringNotContainsString("\x07", $output);
            self::assertStringContainsString('error', $output);
            self::assertStringContainsString('ab]0;pwned', $output);
        } finally {
            unlink($path);
        }
    }

    public function testAPurelyControlledLevelAnswersTheUnknownSpelling(): void
    {
        $path = sys_get_temp_dir() . '/zef-report-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents($path, json_encode([
            'level' => "\x1b\x07",
            'context' => [],
        ], \JSON_THROW_ON_ERROR) . "\n");

        try {
            [$exitCode, $output] = $this->runCommand(['errors:report', $path]);

            self::assertSame(0, $exitCode);
            self::assertStringNotContainsString("\x1b", $output);
            self::assertStringContainsString('unknown', $output);
        } finally {
            unlink($path);
        }
    }

    public function testTheRequestFilterAnswersOnlyTheMatchingRecords(): void
    {
        $path = sys_get_temp_dir() . '/zef-report-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents(
            $path,
            json_encode(['level' => 'error', 'context' => ['request_id' => 'req-a']], \JSON_THROW_ON_ERROR) . "\n"
            . json_encode(['level' => 'error', 'context' => ['request_id' => 'req-b']], \JSON_THROW_ON_ERROR) . "\n",
        );

        try {
            [$exitCode, $output] = $this->runCommand(['errors:report', $path, '--request-id=req-a']);

            self::assertSame(0, $exitCode);
            self::assertStringContainsString('(request_id=req-a)', $output);
            self::assertStringContainsString('Total records: 1', $output);
        } finally {
            unlink($path);
        }
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string}
     */
    private function runCommand(array $arguments): array
    {
        $process = new Process(
            [\PHP_BINARY, \dirname(__DIR__, 2) . '/' . self::COMMAND, ...$arguments],
            \dirname(__DIR__, 2),
        );
        $process->run();

        return [$process->getExitCode() ?? 255, $process->getOutput() . $process->getErrorOutput()];
    }
}
