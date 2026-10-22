<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Zef\Framework\Middleware\ProcessBoundaryFatals;
use Zef\Framework\Middleware\ProcessBoundaryRecords;
use Zef\Framework\Middleware\ProcessBoundaryTransmission;
use Zef\Framework\Middleware\ProcessErrorBoundary;
use Zef\Framework\Middleware\ProcessNativeDiagnostics;
use Zef\Framework\Middleware\ProcessShutdownFatals;
use Zef\Framework\Middleware\ProcessUncaughtThrowables;

/**
 * The engine-dispatch battery for the process error boundary: the three
 * lanes the in-process battery cannot observe honestly, because the
 * runner owns the very engine state they exercise. Each case drives a
 * real PHP process through the boundary's installed arms and asserts on
 * the JSONL records and the exit codes the process leaves behind:
 *
 * - the engine's own dispatch of an E_USER_ERROR -- PHP 8.4 deprecates
 *   the spelling, so the deprecation is recorded first and the user
 *   error itself unwinds into the calling code as the typed conversion
 *   signal;
 * - the masked user error -- an operator mask that excludes the severity
 *   hands it back to the engine, whose fatal abort the shutdown arm then
 *   records as the last resort it exists for;
 * - the genuine engine fatal -- an undefined function call, which
 *   modern PHP raises as an Error throwable rather than a bare fatal,
 *   answered by the uncaught lane the throwable family owns.
 *
 * @internal
 */
#[CoversClass(ProcessErrorBoundary::class)]
#[CoversClass(ProcessBoundaryFatals::class)]
#[CoversClass(ProcessBoundaryRecords::class)]
#[CoversClass(ProcessBoundaryTransmission::class)]
#[CoversClass(ProcessNativeDiagnostics::class)]
#[CoversClass(ProcessUncaughtThrowables::class)]
#[CoversClass(ProcessShutdownFatals::class)]
final class ProcessBoundaryEngineDispatchTest extends TestCase
{
    private string $logPath = '';

    private string $scriptPath = '';

    #[Override]
    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/zef-boundary-engine-' . uniqid() . '.jsonl';
        $this->scriptPath = sys_get_temp_dir() . '/zef-boundary-engine-' . uniqid() . '.php';
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ([$this->logPath, $this->scriptPath] as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }
    }

    public function testTheEngineDispatchesAUserErrorThroughTheTypedLane(): void
    {
        [$exitCode, $output] = $this->runEngineScript(<<<'PHP'
            error_reporting(E_ALL);
            $boundary = new Zef\Framework\Middleware\ProcessErrorBoundary(
                new Zef\Framework\Logging\JsonLineLogger($logPath),
            );
            $boundary->install();
            try {
                trigger_error('The budget import failed', E_USER_ERROR);
                exit(3);
            } catch (Zef\Framework\Exception\NativeErrorException $nativeErrorException) {
                fwrite(STDOUT, 'CAUGHT ' . $nativeErrorException->getMessage() . "\n");
                exit(0);
            }
            PHP);

        self::assertSame(0, $exitCode, "The conversion must unwind into the calling code.\n" . $output);
        self::assertStringContainsString(
            'CAUGHT The budget import failed in ' . $this->scriptPath,
            $output,
        );

        $records = $this->jsonlRecords();
        self::assertCount(
            1,
            $records,
            "The engine's deprecation of the E_USER_ERROR spelling is the one diagnostic recorded.",
        );
        self::assertSame('info', $records[0]['level']);
        self::assertSame('Native diagnostic', $records[0]['message']);
        $message = $this->contextOf($records[0])['diagnostic.message'] ?? null;
        self::assertIsString($message);
        self::assertStringContainsString(
            'Passing E_USER_ERROR to trigger_error() is deprecated',
            $message,
        );
    }

    /**
     * The conversion arm must consume the engine's last-error witness:
     * a converted-and-handled E_USER_ERROR would otherwise still sit in
     * error_get_last(), and the shutdown arm of a later worker recycle
     * would re-record it as a fresh critical fatal -- double counting in
     * logs and metrics, with a second 500 ship attempt attached.
     */
    public function testAConvertedUserErrorIsNotReWitnessedAtTheNextShutdown(): void
    {
        [$exitCode, $output] = $this->runEngineScript(<<<'PHP'
            error_reporting(E_ALL);
            $boundary = new Zef\Framework\Middleware\ProcessErrorBoundary(
                new Zef\Framework\Logging\JsonLineLogger($logPath),
            );
            $boundary->install();
            try {
                trigger_error('The budget import failed', E_USER_ERROR);
                exit(3);
            } catch (Zef\Framework\Exception\NativeErrorException $nativeErrorException) {
                fwrite(STDOUT, 'CAUGHT ' . $nativeErrorException->getMessage() . "\n");
            }
            $boundary->onShutdown();
            exit(0);
            PHP);

        self::assertSame(0, $exitCode, "The conversion must unwind and the recycled shutdown stay silent.\n" . $output);
        self::assertStringContainsString(
            'CAUGHT The budget import failed in ' . $this->scriptPath,
            $output,
        );

        $records = $this->jsonlRecords();
        self::assertCount(
            1,
            $records,
            'The deprecation notice is the one record: the converted user error is never re-witnessed as a fatal.',
        );
    }

    public function testAMaskedUserErrorIsAnsweredByTheShutdownArm(): void
    {
        [$exitCode, $output] = $this->runEngineScript(<<<'PHP'
            error_reporting(E_ALL & ~E_USER_ERROR & ~E_DEPRECATED);
            $boundary = new Zef\Framework\Middleware\ProcessErrorBoundary(
                new Zef\Framework\Logging\JsonLineLogger($logPath),
            );
            $boundary->install();
            trigger_error('The budget import failed', E_USER_ERROR);
            exit(3);
            PHP);

        self::assertSame(255, $exitCode, "The engine's fatal abort owns the exit code.\n" . $output);

        $records = $this->jsonlRecords();
        self::assertCount(1, $records);
        self::assertSame('critical', $records[0]['level']);
        self::assertSame('Uncaught fatal', $records[0]['message']);
        $message = $this->contextOf($records[0])['diagnostic.message'] ?? null;
        self::assertIsString($message);
        self::assertStringContainsString('The budget import failed', $message);
    }

    public function testAnEngineErrorIsAnsweredByTheUncaughtLane(): void
    {
        [$exitCode, $output] = $this->runEngineScript(<<<'PHP'
            error_reporting(E_ALL);
            $boundary = new Zef\Framework\Middleware\ProcessErrorBoundary(
                new Zef\Framework\Logging\JsonLineLogger($logPath),
            );
            $boundary->install();
            zef_boundary_undefined_function();
            exit(3);
            PHP);

        self::assertSame(0, $exitCode, "The engine's own termination owns the exit code.\n" . $output);
        self::assertStringContainsString(
            'zef: Error: Call to undefined function zef_boundary_undefined_function()',
            $output,
            'A modern engine fatal is an Error throwable, answered by the uncaught lane.',
        );

        $records = $this->jsonlRecords();
        self::assertCount(1, $records);
        self::assertSame('critical', $records[0]['level']);
        self::assertSame('Request failed', $records[0]['message']);
        $exceptionClass = $this->contextOf($records[0])['exception_class'] ?? null;
        self::assertIsString($exceptionClass);
        self::assertSame('Error', $exceptionClass);
    }

    /**
     * Writes the engine script with the autoload and the log path bound,
     * then runs it in a real PHP process.
     *
     * @return array{int, string} the exit code and the combined output
     */
    private function runEngineScript(string $body): array
    {
        $autoload = \dirname(__DIR__, 2) . '/vendor/autoload.php';

        $script = <<<PHP
            <?php

            declare(strict_types=1);

            require '{$autoload}';

            \$logPath = '{$this->logPath}';

            {$body}
            PHP;

        self::assertNotFalse(file_put_contents($this->scriptPath, $script));

        $process = new Process([\PHP_BINARY, $this->scriptPath], \dirname(__DIR__, 2));
        $process->run();

        return [$process->getExitCode() ?? 255, $process->getOutput() . $process->getErrorOutput()];
    }

    /**
     * @param array<array-key, mixed> $record one decoded JSONL record
     *
     * @return array<array-key, mixed> the record's context map
     */
    private function contextOf(array $record): array
    {
        $context = $record['context'] ?? null;
        self::assertIsArray($context);

        return $context;
    }

    /**
     * @return list<array<array-key, mixed>> the decoded JSONL records
     */
    private function jsonlRecords(): array
    {
        if (!is_file($this->logPath)) {
            return [];
        }

        $records = [];
        foreach (explode("\n", (string) file_get_contents($this->logPath)) as $line) {
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded, 'Every JSONL line must decode to an object.');
            $records[] = $decoded;
        }

        return $records;
    }
}
