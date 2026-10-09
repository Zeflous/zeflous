<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\WorkflowConcurrencyGate;
use Zef\Framework\Tooling\WorkflowConcurrencyRule;

/**
 * @internal
 */
#[CoversClass(WorkflowConcurrencyGate::class)]
#[CoversClass(WorkflowConcurrencyRule::class)]
final class WorkflowConcurrencyGateTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = (string) tempnam(sys_get_temp_dir(), 'zef-wf-');
        unlink($this->directory);
        mkdir($this->directory, 0o777, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory . '/*');

        foreach (\is_array($files) ? $files : [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testPassesWhenARefScopedPollingWorkflowSupersedesStaleRuns(): void
    {
        $this->write('ci-strict.yml', <<<'YAML'
            name: CI Strict
            concurrency:
              group: ci-strict-${{ github.event_name }}-${{ github.ref }}
              cancel-in-progress: true
            jobs:
              ci-strict:
                steps:
                  - run: curl "https://api.github.com/repos/x/y/commits/$SHA/check-runs"
            YAML);

        $gateResult = $this->gate()->evaluate();

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('1 polling workflow(s)', $gateResult->message);
        self::assertStringContainsString('honour the supersede policy', $gateResult->message);
    }

    public function testFailsWhenARefScopedPollingWorkflowQueuesInsteadOfSuperseding(): void
    {
        $this->write('ci-strict.yml', <<<'YAML'
            name: CI Strict
            concurrency:
              group: ci-strict-${{ github.ref }}
              cancel-in-progress: false
            jobs:
              ci-strict:
                steps:
                  - run: curl "https://api.github.com/repos/x/y/commits/$SHA/check-runs"
            YAML);

        $gateResult = $this->gate()->evaluate();

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('ci-strict.yml', $gateResult->message);
        self::assertStringContainsString('cancel-in-progress: true', $gateResult->message);
    }

    public function testFailsWhenARefScopedPollingWorkflowOmitsCancelInProgress(): void
    {
        $this->write('poller.yml', <<<'YAML'
            name: Poller
            concurrency:
              group: poller-${{ github.ref }}
            jobs:
              poll:
                steps:
                  - run: curl "https://api.github.com/repos/x/y/commits/$SHA/check-runs"
            YAML);

        $gateResult = $this->gate()->evaluate();

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('cancel-in-progress: true', $gateResult->message);
    }

    public function testPassesWhenAPollingWorkflowHasNoConcurrencyBlock(): void
    {
        $this->write('poller.yml', <<<'YAML'
            name: Poller
            jobs:
              poll:
                steps:
                  - run: curl "https://api.github.com/repos/x/y/commits/$SHA/check-runs"
            YAML);

        $gateResult = $this->gate()->evaluate();

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('1 polling workflow(s)', $gateResult->message);
    }

    public function testPassesWhenAPollingWorkflowUsesAGlobalGroup(): void
    {
        // The `auto-merge` lane deliberately queues on a GLOBAL group (FIFO):
        // a stale run cannot block a specific ref's required check, so this is
        // a legitimate design choice and must not be flagged.
        $this->write('auto-merge.yml', <<<'YAML'
            name: Auto Merge
            concurrency:
              group: auto-merge
              cancel-in-progress: false
            jobs:
              auto-merge:
                steps:
                  - run: curl "https://api.github.com/repos/x/y/commits/$SHA/check-runs"
            YAML);

        $gateResult = $this->gate()->evaluate();

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('1 polling workflow(s)', $gateResult->message);
    }

    public function testPassesWhenConcurrencyIsAScalarGroup(): void
    {
        $this->write('poller.yml', <<<'YAML'
            name: Poller
            concurrency: poller-group
            jobs:
              poll:
                steps:
                  - run: curl "https://api.github.com/repos/x/y/commits/$SHA/check-runs"
            YAML);

        $gateResult = $this->gate()->evaluate();

        self::assertTrue($gateResult->passed);
    }

    public function testIgnoresANonPollingWorkflowThatQueues(): void
    {
        $this->write('release.yml', <<<'YAML'
            name: Release
            concurrency:
              group: release-${{ github.ref }}
              cancel-in-progress: false
            jobs:
              release:
                steps:
                  - run: echo "no polling here"
            YAML);

        $gateResult = $this->gate()->evaluate();

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('0 polling workflow(s)', $gateResult->message);
    }

    public function testFailsOnUnparseableYamlThatPolls(): void
    {
        $this->write('broken.yml', "name: Broken\n  bad: [unclosed\n# check-runs\n");

        $gateResult = $this->gate()->evaluate();

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('unparseable YAML', $gateResult->message);
    }

    public function testFailsWhenTheDocumentIsNotAMapping(): void
    {
        $this->write('scalar.yml', "check-runs\n");

        $gateResult = $this->gate()->evaluate();

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('not a mapping', $gateResult->message);
    }

    public function testScansTheYamlExtensionToo(): void
    {
        $this->write('poller.yaml', <<<'YAML'
            name: Poller
            concurrency:
              group: poller-${{ github.ref }}
              cancel-in-progress: true
            jobs:
              poll:
                steps:
                  - run: curl "https://api.github.com/repos/x/y/commits/$SHA/check-runs"
            YAML);

        $gateResult = $this->gate()->evaluate();

        self::assertTrue($gateResult->passed);
        self::assertStringContainsString('1 polling workflow(s)', $gateResult->message);
    }

    public function testReportsEveryViolation(): void
    {
        $this->write('a.yml', "name: A\nconcurrency:\n  group: a-\${{ github.ref }}\n"
            . "  cancel-in-progress: false\n# check-runs\n");
        $this->write('b.yml', "name: B\nconcurrency:\n  group: b-\${{ github.ref }}\n"
            . "# check-runs\n");

        $gateResult = $this->gate()->evaluate();

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('2 polling workflow(s) violate', $gateResult->message);
        self::assertStringContainsString('a.yml', $gateResult->message);
        self::assertStringContainsString('b.yml', $gateResult->message);
    }

    public function testRuleReturnsNullForACompliantWorkflow(): void
    {
        $workflowConcurrencyRule = new WorkflowConcurrencyRule();
        $yaml = "concurrency:\n  group: g-\${{ github.ref }}\n  cancel-in-progress: true\n";

        self::assertNull($workflowConcurrencyRule->violation('ok.yml', $yaml));
    }

    public function testRuleReturnsTheViolationForAQueuingWorkflow(): void
    {
        $workflowConcurrencyRule = new WorkflowConcurrencyRule();
        $yaml = "concurrency:\n  group: g-\${{ github.ref }}\n  cancel-in-progress: false\n";

        $violation = $workflowConcurrencyRule->violation('bad.yml', $yaml);

        self::assertIsString($violation);
        self::assertStringContainsString('bad.yml', $violation);
    }

    private function gate(): WorkflowConcurrencyGate
    {
        return new WorkflowConcurrencyGate($this->directory);
    }

    private function write(string $name, string $contents): void
    {
        file_put_contents($this->directory . '/' . $name, $contents);
    }
}
