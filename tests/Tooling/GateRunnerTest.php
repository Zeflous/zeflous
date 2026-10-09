<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\GateRunner;
use Zef\Framework\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(GateRunner::class)]
final class GateRunnerTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = (string) tempnam(sys_get_temp_dir(), 'zef-gate-');
        unlink($this->root);
        mkdir($this->root . '/build/infection', 0o777, true);
        mkdir($this->root . '/src', 0o777, true);
        file_put_contents($this->root . '/src/Example.php', '<?php');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testCoverageGateReadsTheCloverReport(): void
    {
        file_put_contents(
            $this->root . '/build/clover.xml',
            '<coverage><project><metrics statements="10" coveredstatements="10"/></project></coverage>',
        );

        $gateResult = $this->runner()->run('coverage', ['90']);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('Coverage gate PASSED', $gateResult->message);
    }

    public function testMutationGateReadsTheInfectionReport(): void
    {
        file_put_contents(
            $this->root . '/build/infection/infection.json',
            '{"stats":{"msi":100,"coveredCodeMsi":100,"mutationCodeCoverage":100}}',
        );

        self::assertTrue($this->runner()->run('mutation', ['100'])->passed);
    }

    public function testCoverageGateUsesTheProvidedThreshold(): void
    {
        file_put_contents(
            $this->root . '/build/clover.xml',
            '<coverage><project><metrics statements="10" coveredstatements="6"/></project></coverage>',
        );

        $gateResult = $this->runner()->run('coverage', ['50']);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('required: 50.00%', $gateResult->message);
    }

    public function testMutationGateUsesTheProvidedFloor(): void
    {
        file_put_contents(
            $this->root . '/build/infection/infection.json',
            '{"stats":{"msi":60,"coveredCodeMsi":60,"mutationCodeCoverage":100}}',
        );

        $gateResult = $this->runner()->run('mutation', ['50']);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('floor: 50.00%', $gateResult->message);
    }

    public function testBaselineGatePassesWithoutABaselineFile(): void
    {
        self::assertTrue($this->runner()->run('baseline', [])->passed);
    }

    public function testBaselineGateFailsWithANonEmptyBaseline(): void
    {
        $baseline = "parameters:\n    ignoreErrors:\n        - message: '#x#'\n";
        file_put_contents($this->root . '/phpstan-baseline.neon', $baseline);

        self::assertFalse($this->runner()->run('baseline', [])->passed);
    }

    public function testAuditGateUsesTheAllowlistFromComposerJson(): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            '{"config":{"audit":{"ignore":["a/b"]}}}',
        );

        $runner = $this->runner(static fn (string $command): array => [
            0,
            '{"advisories":{"a/b":[{}]},"abandoned":{}}',
        ]);

        self::assertTrue($runner->run('audit', [])->passed);
    }

    public function testAuditGateFailsWhenThePayloadIsEmpty(): void
    {
        $runner = $this->runner(static fn (string $command): array => [0, '']);

        try {
            $runner->run('audit', []);
            self::fail('An empty audit payload must fail the gate.');
        } catch (ToolingException $toolingException) {
            self::assertStringContainsString('produced no JSON payload', $toolingException->getMessage());
        }
    }

    public function testAuditGateKeepsEveryAllowlistEntry(): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            '{"config":{"audit":{"ignore":["a/b","c/d"]}}}',
        );

        $runner = $this->runner(static fn (string $command): array => [
            0,
            '{"advisories":{"a/b":[{}],"c/d":[{}]},"abandoned":{}}',
        ]);

        self::assertTrue($runner->run('audit', [])->passed);
    }

    public function testAuditGateToleratesAMissingComposerJson(): void
    {
        $runner = $this->runner(static fn (string $command): array => [0, '{"advisories":{"a/b":[{}]}}']);

        self::assertFalse($runner->run('audit', [])->passed);
    }

    public function testAuditGateToleratesANonArrayConfigSection(): void
    {
        // A present-but-non-array 'config' must be treated as "no allow-list"
        // rather than tripping a type error while walking the composer.json
        // tree. Reading a key off a string without the guard would raise, so
        // this pins the fail-safe branch of the traversal.
        file_put_contents($this->root . '/composer.json', '{"config":"not-an-array"}');

        $runner = $this->runner(static fn (string $command): array => [0, '{"advisories":{"a/b":[{}]}}']);

        // Empty allow-list => the advisory is not exempt => the gate fails.
        self::assertFalse($runner->run('audit', [])->passed);
    }

    public function testLintGatePassesWhenTheProcessSucceeds(): void
    {
        $runner = $this->runner(static fn (string $command): array => [0, 'No syntax errors']);

        self::assertTrue($runner->run('lint', [])->passed);
    }

    public function testLintGateFailsWhenTheProcessFails(): void
    {
        $runner = $this->runner(static fn (string $command): array => [255, 'Parse error']);

        self::assertFalse($runner->run('lint', [])->passed);
    }

    public function testWorkflowConcurrencyGateAuditsTheWorkflowDirectory(): void
    {
        mkdir($this->root . '/.github/workflows', 0o777, true);
        file_put_contents(
            $this->root . '/.github/workflows/ci-strict.yml',
            "concurrency:\n  group: ci-strict-\${{ github.sha }}\n",
        );

        try {
            $gateResult = $this->runner()->run('workflow-concurrency', []);

            self::assertTrue($gateResult->passed);
            self::assertSame('Workflow concurrency gate PASSED: 1 workflow(s) audited.', $gateResult->message);
        } finally {
            unlink($this->root . '/.github/workflows/ci-strict.yml');
            rmdir($this->root . '/.github/workflows');
            rmdir($this->root . '/.github');
        }
    }

    public function testSmokeGateRunsThePersistentWorkerSmoke(): void
    {
        $gateResult = $this->runner()->run('smoke', []);

        self::assertTrue($gateResult->passed);
        self::assertStringStartsWith('Persistent-worker smoke:', $gateResult->message);
        self::assertStringContainsString('20000 iterations', $gateResult->message);
        self::assertStringContainsString('budget 2000000', $gateResult->message);
        self::assertStringContainsString('Persistent-worker smoke PASSED', $gateResult->message);
    }

    public function testSmokeGateUsesTheConfiguredIterationsAndBudget(): void
    {
        $gateRunner = new GateRunner(
            $this->root,
            static fn (string $command): array => [0, ''],
            5,
            2_000_000,
        );

        $gateResult = $gateRunner->run('smoke', []);

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('5 iterations', $gateResult->message);
        self::assertStringContainsString('budget 2000000', $gateResult->message);
        self::assertStringContainsString('Persistent-worker smoke PASSED', $gateResult->message);
    }

    public function testUnknownGateThrows(): void
    {
        $this->expectException(ToolingException::class);

        $this->runner()->run('nope', []);
    }

    /**
     * @param null|(callable(string): array{0: int, 1: string}) $processRunner
     */
    private function runner(?callable $processRunner = null): GateRunner
    {
        $processRunner ??= static fn (string $command): array => [0, ''];

        return new GateRunner($this->root, $processRunner(...));
    }

    private function removeTree(string $root): void
    {
        $paths = [
            $root . '/build/infection/infection.json',
            $root . '/build/infection',
            $root . '/build/clover.xml',
            $root . '/build',
            $root . '/src/Example.php',
            $root . '/src',
            $root . '/phpstan-baseline.neon',
            $root . '/composer.json',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                rmdir($path);
            }
        }

        rmdir($root);
    }
}
