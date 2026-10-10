<?php

declare(strict_types=1);

namespace Zef\Test\Scripts;

use FilesystemIterator;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Process\Process;
use Zef\Framework\Tooling\GateRunner;

/**
 * Exercises the SARIF post-processor end to end as a real subprocess, the
 * same way `.github/workflows/archeology.yml` invokes it.
 *
 * @internal
 */
#[CoversNothing]
final class ArcheologyGateTest extends TestCase
{
    private const string GATE_SCRIPT = 'scripts/ci/archeology-gate.php';

    private const string SARIF_SCHEMA_URL = 'https://raw.githubusercontent.com/oasis-tcs/sarif-spec/'
        . '/master/Schemata/sarif-schema-2.1.0.json';

    private string $workDir = '';

    #[Override]
    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/zef-archeology-gate-' . uniqid('', false);

        mkdir($this->workDir . '/src/Example', 0o777, true);
        touch($this->workDir . '/src/Example/File.php');
    }

    #[Override]
    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->workDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            $path = $file->getPathname();

            if ($file->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($this->workDir);
    }

    public function testAbsoluteUrisBelowTheRootAreRewrittenToRepositoryRelativePaths(): void
    {
        $sarifPath = $this->writeSarif([
            $this->buildResult('error', 'PCA/class/GodClass', [
                'uri' => $this->workDir . '/src/Example/File.php',
                'uriBaseId' => 'SRCROOT',
                'startLine' => 12,
                'endLine' => 12,
            ], 'God class detected.'),
        ]);

        [$exitCode, $output] = $this->runGate($sarifPath, $this->workDir);

        $results = $this->readResults($sarifPath);

        self::assertSame(1, $exitCode, $output);
        self::assertCount(1, $results);
        self::assertSame('src/Example/File.php', $this->uriOf($results[0]));
        self::assertArrayNotHasKey('uriBaseId', $this->artifactOf($results[0]));
    }

    public function testWarningLevelFindingsBelowTheRootAreExcludedFromTheUploadedSarif(): void
    {
        $sarifPath = $this->writeSarif([
            $this->buildResult('warning', 'PCA/function/Effort', [
                'uri' => $this->workDir . '/src/Example/File.php',
                'startLine' => 12,
                'endLine' => 12,
            ], 'Effort more than 30% above average effort.'),
        ]);

        [$exitCode, $output] = $this->runGate($sarifPath, $this->workDir);

        self::assertSame(0, $exitCode, $output);
        self::assertSame([], $this->readResults($sarifPath));
        self::assertStringContainsString('1 relative-metric warning(s) excluded', $output);
    }

    public function testClassFqnUrisAreRewrittenWhenTheMappedFileExists(): void
    {
        $sarifPath = $this->writeSarif([
            $this->buildResult('error', 'PCA/class/GodClass', [
                'uri' => GateRunner::class,
                'startLine' => 33,
                'endLine' => 33,
            ], 'God class detected.'),
        ]);

        [$exitCode, $output] = $this->runGate($sarifPath, \dirname(__DIR__, 2));

        $results = $this->readResults($sarifPath);

        self::assertSame(1, $exitCode, $output);
        self::assertCount(1, $results);
        self::assertSame('src/Zef/Framework/Tooling/GateRunner.php', $this->uriOf($results[0]));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->lineHashOf($results[0]));
    }

    public function testUnresolvableClassFqnUrisAreLeftUntouched(): void
    {
        $sarifPath = $this->writeSarif([
            $this->buildResult('error', 'PCA/class/GodClass', [
                'uri' => 'Zef\Framework\Tooling\NoSuchClass',
                'startLine' => 7,
                'endLine' => 7,
            ], 'God class detected.'),
        ]);

        [$exitCode, $output] = $this->runGate($sarifPath, \dirname(__DIR__, 2));

        $results = $this->readResults($sarifPath);

        self::assertSame(1, $exitCode, $output);
        self::assertCount(1, $results);
        self::assertSame('Zef\Framework\Tooling\NoSuchClass', $this->uriOf($results[0]));
    }

    public function testIdenticalResultsAreCollapsedIntoOneFinding(): void
    {
        $godClass = fn (): array => $this->buildErrorResult(
            'PCA/class/GodClass',
            'src/Zef/Framework/Tooling/GateRunner.php',
            33,
            'God class detected.',
        );

        $sarifPath = $this->writeSarif([$godClass(), $godClass()]);

        [$exitCode, $output] = $this->runGate($sarifPath, $this->workDir);

        self::assertSame(1, $exitCode, $output);
        self::assertCount(1, $this->readResults($sarifPath));
        self::assertStringContainsString('collapsed 1 duplicate result(s)', $output);
    }

    public function testWarningLevelFindingsAreExcludedFromTheUploadedSarif(): void
    {
        $sarifPath = $this->writeSarif([
            $this->buildResult('warning', 'PCA/class/LCOM', [
                'uri' => 'src/Zef/Framework/Tooling/GateResult.php',
                'startLine' => 10,
                'endLine' => 10,
            ], 'LCOM is more than 30% above average LCOM.'),
        ]);

        [$exitCode, $output] = $this->runGate($sarifPath, $this->workDir);

        self::assertSame(0, $exitCode, $output);
        self::assertSame([], $this->readResults($sarifPath));
        self::assertStringContainsString('0 error-level finding(s) kept', $output);
        self::assertStringContainsString('gate PASSED', $output);
    }

    public function testAbsentLevelIsTreatedAsErrorPerTheSarifSpecification(): void
    {
        $sarifPath = $this->writeSarif([
            $this->buildResult('', 'PCA/class/SecuritySmell', [
                'uri' => 'src/Example/File.php',
                'startLine' => 3,
                'endLine' => 3,
            ], 'Security smell detected.'),
        ]);

        // The helper removed the level key entirely (an absent level), which
        // SARIF 2.1.0 defines as `error`; the gate must fail closed and keep
        // the finding rather than silently dropping it.
        [$exitCode, $output] = $this->runGate($sarifPath, $this->workDir);

        self::assertSame(1, $exitCode, $output);
        self::assertCount(1, $this->readResults($sarifPath));
    }

    public function testExistingFingerprintsArePreserved(): void
    {
        $sarifPath = $this->writeSarif([
            $this->buildErrorResult('PCA/class/GodClass', 'src/Example/File.php', 3, 'God class detected.')
            + ['partialFingerprints' => ['primaryLocationLineHash' => 'stable-hash-from-tool']],
        ]);

        [$exitCode, $output] = $this->runGate($sarifPath, $this->workDir);

        $results = $this->readResults($sarifPath);

        self::assertSame(1, $exitCode, $output);
        self::assertCount(1, $results);
        self::assertSame('stable-hash-from-tool', $this->lineHashOf($results[0]));
    }

    public function testMissingSarifReportFailsWithExitCodeTwo(): void
    {
        [$exitCode, $output] = $this->runGate($this->workDir . '/does-not-exist.sarif.json', $this->workDir);

        self::assertSame(2, $exitCode, $output);
        self::assertStringContainsString('SARIF report not found', $output);
    }

    public function testInvalidJsonSarifReportFailsWithExitCodeTwo(): void
    {
        $sarifPath = $this->workDir . '/invalid.sarif.json';
        file_put_contents($sarifPath, '{"runs": [}}');

        [$exitCode, $output] = $this->runGate($sarifPath, $this->workDir);

        self::assertSame(2, $exitCode, $output);
        self::assertStringContainsString('not valid JSON', $output);
    }

    /**
     * @param array{uri: string, uriBaseId?: string, startLine: int, endLine: int} $location
     *
     * @return array<string, mixed>
     */
    private function buildResult(string $level, string $ruleId, array $location, string $message): array
    {
        $artifact = ['uri' => $location['uri']];

        if (\array_key_exists('uriBaseId', $location)) {
            $artifact['uriBaseId'] = $location['uriBaseId'];
        }

        $result = [
            'level' => $level,
            'ruleId' => $ruleId,
            'message' => ['text' => $message],
            'locations' => [
                [
                    'physicalLocation' => [
                        'artifactLocation' => $artifact,
                        'region' => [
                            'startLine' => $location['startLine'],
                            'endLine' => $location['endLine'],
                        ],
                    ],
                ],
            ],
        ];

        if ($level === '') {
            // The caller intends to test an absent level; SARIF 2.1.0 defines
            // an absent level as `error`, so the key is removed entirely.
            unset($result['level']);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildErrorResult(string $ruleId, string $uri, int $line, string $message): array
    {
        return $this->buildResult(
            'error',
            $ruleId,
            ['uri' => $uri, 'startLine' => $line, 'endLine' => $line],
            $message,
        );
    }

    /**
     * @param list<array<string, mixed>> $results
     */
    private function writeSarif(array $results): string
    {
        $sarifPath = $this->workDir . '/report.sarif.json';

        file_put_contents($sarifPath, json_encode([
            'version' => '2.1.0',
            '$schema' => self::SARIF_SCHEMA_URL,
            'runs' => [
                [
                    'tool' => ['driver' => ['name' => 'PhpCodeArcheology', 'version' => '2.11.3']],
                    'results' => $results,
                ],
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));

        return $sarifPath;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runGate(string $sarifPath, string $root): array
    {
        $process = new Process([\PHP_BINARY, \dirname(__DIR__, 2) . '/' . self::GATE_SCRIPT, $sarifPath, $root]);
        $process->run();

        return [$process->getExitCode() ?? 255, $process->getOutput() . $process->getErrorOutput()];
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function readResults(string $sarifPath): array
    {
        $raw = file_get_contents($sarifPath);

        if (!\is_string($raw)) {
            throw new RuntimeException('SARIF fixture could not be read back.');
        }

        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new RuntimeException('SARIF fixture did not decode to an object.');
        }

        $runs = $decoded['runs'] ?? null;

        if (!\is_array($runs)) {
            throw new RuntimeException('SARIF fixture has no runs.');
        }

        $run = $runs[0] ?? null;

        if (!\is_array($run)) {
            throw new RuntimeException('SARIF fixture has no first run.');
        }

        $results = $run['results'] ?? null;

        if (!\is_array($results)) {
            throw new RuntimeException('SARIF fixture run has no results.');
        }

        $list = [];

        foreach ($results as $result) {
            if (!\is_array($result)) {
                throw new RuntimeException('SARIF fixture result is not an object.');
            }

            $list[] = $result;
        }

        return $list;
    }

    /**
     * @param array<array-key, mixed> $result
     *
     * @return array<array-key, mixed>
     */
    private function artifactOf(array $result): array
    {
        $locations = $result['locations'] ?? null;

        if (!\is_array($locations)) {
            throw new RuntimeException('Result has no locations.');
        }

        $location = $locations[0] ?? null;

        if (!\is_array($location)) {
            throw new RuntimeException('Result has no primary location.');
        }

        $physical = $location['physicalLocation'] ?? null;

        if (!\is_array($physical)) {
            throw new RuntimeException('Primary location has no physicalLocation.');
        }

        $artifact = $physical['artifactLocation'] ?? null;

        if (!\is_array($artifact)) {
            throw new RuntimeException('Physical location has no artifactLocation.');
        }

        return $artifact;
    }

    /**
     * @param array<array-key, mixed> $result
     */
    private function uriOf(array $result): string
    {
        $uri = $this->artifactOf($result)['uri'] ?? null;

        if (!\is_string($uri)) {
            throw new RuntimeException('Artifact location has no string uri.');
        }

        return $uri;
    }

    /**
     * @param array<array-key, mixed> $result
     */
    private function lineHashOf(array $result): string
    {
        $fingerprints = $result['partialFingerprints'] ?? null;

        if (!\is_array($fingerprints)) {
            throw new RuntimeException('Result has no partialFingerprints.');
        }

        $hash = $fingerprints['primaryLocationLineHash'] ?? null;

        if (!\is_string($hash)) {
            throw new RuntimeException('Result has no string primaryLocationLineHash.');
        }

        return $hash;
    }
}
