<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Middleware\ProcessErrorBoundary;
use Zef\Framework\Middleware\ProcessErrorBoundaryFactory;
use Zef\Framework\Telemetry\CounterMeter;

/**
 * Direct battery for the process boundary's construction lane: the knob
 * grammar folds fail closed, the installation knob answers from the
 * frozen configuration alone, the JSONL logger and the metrics fold wire
 * through, and the disclosure-gated debug posture is observed through the
 * emission the factory-built boundary ships -- the SAPI construction seam
 * answering the HTTP branch the running process's own CLI spelling never
 * would.
 *
 * @internal
 */
#[CoversClass(ProcessErrorBoundaryFactory::class)]
#[CoversClass(ProcessErrorBoundary::class)]
final class ProcessErrorBoundaryFactoryTest extends TestCase
{
    private string $logPath = '';

    private string $routedErrorLog = '';

    private string|false $previousErrorLog = false;

    private int|false $previousReporting = false;

    private string|false $previousEnvironment = false;

    #[Override]
    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/zef-process-boundary-factory-' . uniqid() . '.jsonl';
        $this->routedErrorLog = sys_get_temp_dir() . '/zef-process-boundary-factory-errorlog-' . uniqid() . '.log';
        $this->previousErrorLog = (string) ini_set('error_log', $this->routedErrorLog);
        $this->previousReporting = error_reporting(E_ALL);
        $this->previousEnvironment = getenv('APP_ENV');
        putenv('APP_ENV=local');
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->previousEnvironment === false) {
            putenv('APP_ENV');
        } else {
            putenv('APP_ENV=' . $this->previousEnvironment);
        }

        error_reporting((int) $this->previousReporting);
        ini_set('error_log', (string) $this->previousErrorLog);

        foreach ([$this->logPath, $this->routedErrorLog] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testTheKnobDefaultsAnswerANotInstalledBoundary(): void
    {
        self::assertFalse(ProcessErrorBoundaryFactory::wantsInstallation(new Config()));
    }

    public function testTheKnobAnswersTheInstallationAsk(): void
    {
        self::assertTrue(
            ProcessErrorBoundaryFactory::wantsInstallation(
                new Config(['http' => ['error_handling' => ['process_boundary' => true]]]),
            ),
        );
        self::assertFalse(
            ProcessErrorBoundaryFactory::wantsInstallation(
                new Config(['http' => ['error_handling' => ['process_boundary' => false]]]),
            ),
        );
    }

    public function testTheKnobRefusesANonBooleanSpelling(): void
    {
        $this->assertConfigException(
            'http.error_handling.process_boundary must be a boolean.',
            static fn (): bool => ProcessErrorBoundaryFactory::wantsInstallation(
                new Config(['http' => ['error_handling' => ['process_boundary' => 'yes']]]),
            ),
        );
    }

    public function testTheDebugKnobRefusesANonBooleanSpelling(): void
    {
        $this->assertConfigException(
            'http.error_handling.debug must be a boolean.',
            static fn (): ProcessErrorBoundary => ProcessErrorBoundaryFactory::fromConfig(
                new Config(['http' => ['error_handling' => ['debug' => 'true']]]),
            ),
        );
    }

    public function testTheLogPathKnobRefusesANonStringSpelling(): void
    {
        $this->assertConfigException(
            'http.error_handling.log_path must be a string.',
            static fn (): ProcessErrorBoundary => ProcessErrorBoundaryFactory::fromConfig(
                new Config(['http' => ['error_handling' => ['log_path' => 42]]]),
            ),
        );
    }

    public function testTheMetricsKnobRefusesANonBooleanSpelling(): void
    {
        $this->assertConfigException(
            'observability.metrics.enabled must be a boolean.',
            static fn (): ProcessErrorBoundary => ProcessErrorBoundaryFactory::fromConfig(
                new Config(['observability' => ['metrics' => ['enabled' => 'on']]]),
            ),
        );
    }

    public function testTheBuiltBoundaryWritesThroughTheConfiguredJsonlLogger(): void
    {
        $processErrorBoundary = ProcessErrorBoundaryFactory::fromConfig(new Config([
            'http' => ['error_handling' => ['log_path' => $this->logPath]],
        ]));

        $owned = $processErrorBoundary->onNativeDiagnostic(
            E_WARNING,
            'Undefined variable $budget',
            '/var/app/src/budget.php',
            42,
        );

        self::assertTrue($owned);
        $records = $this->jsonlRecords();
        self::assertCount(1, $records);
        self::assertSame('warning', $records[0]['level']);
        self::assertSame('Native diagnostic', $records[0]['message']);
        $context = $records[0]['context'] ?? null;
        self::assertIsArray($context);
        $message = $context['diagnostic.message'] ?? null;
        self::assertIsString($message);
        self::assertSame('Undefined variable $budget', $message);
    }

    public function testTheBuiltBoundaryWithoutALogPathRoutesRecordsToTheSapiLog(): void
    {
        $processErrorBoundary = ProcessErrorBoundaryFactory::fromConfig(new Config());

        $owned = $processErrorBoundary->onNativeDiagnostic(
            E_WARNING,
            'Undefined variable $budget',
            '/var/app/src/budget.php',
            42,
        );

        self::assertTrue($owned);
        self::assertStringContainsString('"message":"Native diagnostic"', $this->routedErrorLogContents());
    }

    public function testTheDebugPostureIsGatedOnTheEnvironment(): void
    {
        putenv('APP_ENV=production');
        $productionOutput = $this->uncaughtAnswerOutput(new Config([
            'http' => ['error_handling' => ['debug' => true, 'log_path' => $this->logPath]],
        ]));
        unlink($this->logPath);

        putenv('APP_ENV=local');
        $localOutput = $this->uncaughtAnswerOutput(new Config([
            'http' => ['error_handling' => ['debug' => true, 'log_path' => $this->logPath]],
        ]));

        self::assertStringContainsString('An error occurred', $productionOutput);
        self::assertStringNotContainsString('The worker observed an impossible state.', $productionOutput);

        self::assertStringContainsString('The worker observed an impossible state.', $localOutput);
    }

    public function testTheMetricsFoldWiresTheCounterOnlyWhenEnabled(): void
    {
        $enabledMeter = new CounterMeter();
        $this->uncaughtAnswerOutput(new Config([
            'http' => ['error_handling' => ['log_path' => $this->logPath]],
            'observability' => ['metrics' => ['enabled' => true]],
        ]), $enabledMeter);
        self::assertNotSame([], $enabledMeter->snapshot());
        unlink($this->logPath);

        $disabledMeter = new CounterMeter();
        $this->uncaughtAnswerOutput(new Config([
            'http' => ['error_handling' => ['log_path' => $this->logPath]],
            'observability' => ['metrics' => ['enabled' => false]],
        ]), $disabledMeter);
        self::assertSame([], $disabledMeter->snapshot());
    }

    /**
     * Observes the answer the factory-built boundary ships for one
     * uncaught throwable, capturing the emission the HTTP branch runs.
     */
    private function uncaughtAnswerOutput(Config $config, ?CounterMeter $meter = null): string
    {
        $processErrorBoundary = ProcessErrorBoundaryFactory::fromConfig($config, $meter, 'fpm-fcgi');

        ob_start();
        try {
            $processErrorBoundary->onUncaughtThrowable(
                new RuntimeException('The worker observed an impossible state.'),
            );
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertNotSame('', $output, 'The HTTP branch must ship an answer.');

        return $output;
    }

    /**
     * @param callable():mixed $construction the construction that must fail closed
     */
    private function assertConfigException(string $expectedMessage, callable $construction): void
    {
        try {
            $construction();
            self::fail('The malformed knob must fail closed.');
        } catch (ConfigException $configException) {
            self::assertSame($expectedMessage, $configException->getMessage());
        }
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

    /**
     * Reads back whatever reached the routed SAPI error log (the empty
     * string when nothing did).
     */
    private function routedErrorLogContents(): string
    {
        if (!is_file($this->routedErrorLog)) {
            return '';
        }

        $contents = file_get_contents($this->routedErrorLog);
        self::assertIsString($contents, 'The routed error log must be readable.');

        return $contents;
    }
}
