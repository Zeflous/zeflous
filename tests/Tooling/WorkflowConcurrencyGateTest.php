<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\GateResult;
use Zef\Framework\Tooling\WorkflowConcurrencyGate;
use Zef\Framework\Tooling\WorkflowConcurrencyReport;

/**
 * @internal
 */
#[CoversClass(WorkflowConcurrencyGate::class)]
#[CoversClass(WorkflowConcurrencyReport::class)]
final class WorkflowConcurrencyGateTest extends TestCase
{
    private const string COMMIT_GROUP = 'ci-strict-${{ github.event_name }}-'
        . '${{ github.event.pull_request.head.sha || github.sha }}';

    private const string PUSH_GROUP = 'ci-strict-${{ github.event_name }}-${{ github.sha }}';

    private const string HEAD_GROUP = 'ci-strict-${{ github.event_name }}-'
        . '${{ github.event.pull_request.head.sha }}';

    private const string BRANCH_GROUP = 'ci-strict-${{ github.event_name }}-${{ github.ref }}';

    public function testPassesForArrayExpansionAndCommitKeyedAggregator(): void
    {
        $gateResult = $this->gate([
            'ci-strict.yml' => $this->aggregator(self::COMMIT_GROUP, 'for name in "${required[@]}"; do'),
        ]);

        self::assertTrue($gateResult->passed);
        self::assertSame('Workflow concurrency gate PASSED: 1 workflow(s) audited.', $gateResult->message);
    }

    public function testFailsOnBareArrayExpansion(): void
    {
        $gateResult = $this->gate([
            'ci-strict.yml' => $this->aggregator(self::COMMIT_GROUP, 'for name in ${required}; do'),
        ]);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Workflow concurrency gate FAILED: ci-strict.yml expands a bash array without [@] (word-splitting).',
            $gateResult->message,
        );
    }

    public function testFailsWhenTheAggregatorGroupIsBranchKeyed(): void
    {
        $gateResult = $this->gate([
            'ci-strict.yml' => $this->aggregator(self::BRANCH_GROUP, 'for name in "${required[@]}"; do'),
        ]);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Workflow concurrency gate FAILED: ci-strict.yml must key its concurrency group '
            . 'on the commit sha, not the branch (found: ' . self::BRANCH_GROUP . ').',
            $gateResult->message,
        );
    }

    public function testFailsWhenTheAggregatorHasNoConcurrencyGroup(): void
    {
        $gateResult = $this->gate([
            'ci-strict.yml' => "name: CI Strict\njobs:\n  x:\n    steps:\n      - run: |\n"
                . "          required=(\"CI Static\")\n"
                . "          for name in \"\${required[@]}\"; do :; done\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Workflow concurrency gate FAILED: ci-strict.yml must key its concurrency group '
            . 'on the commit sha, not the branch (found: none).',
            $gateResult->message,
        );
    }

    public function testAcceptsAPushShaOnlyGroup(): void
    {
        $gateResult = $this->gate([
            'ci-strict.yml' => $this->aggregator(self::PUSH_GROUP, 'for name in "${required[@]}"; do'),
        ]);

        self::assertTrue($gateResult->passed);
    }

    public function testAcceptsAPullRequestHeadShaOnlyGroup(): void
    {
        $gateResult = $this->gate([
            'ci-strict.yml' => $this->aggregator(self::HEAD_GROUP, 'for name in "${required[@]}"; do'),
        ]);

        self::assertTrue($gateResult->passed);
    }

    public function testIgnoresBranchKeyedGroupsOnNonAggregatorWorkflows(): void
    {
        $gateResult = $this->gate([
            'ci-static.yml' => "concurrency:\n  group: " . self::BRANCH_GROUP . "\n",
        ]);

        self::assertTrue($gateResult->passed);
    }

    public function testPassesWhenThereAreNoWorkflows(): void
    {
        $gateResult = $this->gate([]);

        self::assertTrue($gateResult->passed);
        self::assertSame('Workflow concurrency gate PASSED: 0 workflow(s) audited.', $gateResult->message);
    }

    public function testReportsEveryFailureAcrossWorkflows(): void
    {
        $gateResult = $this->gate([
            'ci-strict.yml' => $this->aggregator(self::BRANCH_GROUP, 'for name in ${required}; do'),
        ]);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Workflow concurrency gate FAILED: ci-strict.yml expands a bash array without [@] (word-splitting). '
            . 'ci-strict.yml must key its concurrency group on the commit sha, not the branch '
            . '(found: ' . self::BRANCH_GROUP . ').',
            $gateResult->message,
        );
    }

    public function testIgnoresBareExpansionOfANonArrayVariable(): void
    {
        $gateResult = $this->gate([
            'ci-strict.yml' => $this->aggregator(self::COMMIT_GROUP, 'echo ${GITHUB_SHA}'),
        ]);

        self::assertTrue($gateResult->passed);
    }

    public function testAllowsIndexedArrayExpansion(): void
    {
        $gateResult = $this->gate([
            'ci-strict.yml' => $this->aggregator(self::COMMIT_GROUP, 'echo "${required[0]}"'),
        ]);

        self::assertTrue($gateResult->passed);
    }

    public function testIgnoresBareExpansionMentionedOnlyInAComment(): void
    {
        $contents = "concurrency:\n  group: " . self::COMMIT_GROUP . "\n"
            . "run: |\n"
            . "  # a bare \${required} would word-split; the array form is used below\n"
            . "  required=(\"CI Static\" \"CI Coverage\")\n"
            . "  for name in \"\${required[@]}\"; do :; done\n";

        $gateResult = $this->gate(['ci-strict.yml' => $contents]);

        self::assertTrue($gateResult->passed);
    }

    public function testDetectsBareExpansionAmongSeveralArrays(): void
    {
        $contents = "concurrency:\n  group: " . self::COMMIT_GROUP . "\n"
            . "run: |\n"
            . "  lanes=(\"a\" \"b\")\n"
            . "  names=(\"x y\" \"z\")\n"
            . "  for n in \"\${lanes[@]}\"; do echo \${names}; done\n";

        $gateResult = $this->gate(['ci-strict.yml' => $contents]);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('word-splitting', $gateResult->message);
    }

    private function aggregator(string $group, string $loop): string
    {
        return "name: CI Strict\n"
            . "concurrency:\n"
            . '  group: ' . $group . "\n"
            . "  cancel-in-progress: false\n"
            . "jobs:\n"
            . "  ci-strict:\n"
            . "    steps:\n"
            . "      - run: |\n"
            . "          required=(\"CI Static\" \"CI Coverage\" \"CI Infection\" \"CI Bench\")\n"
            . '          ' . $loop . "\n";
    }

    /**
     * @param array<string, string> $workflows
     */
    private function gate(array $workflows): GateResult
    {
        $root = (string) tempnam(sys_get_temp_dir(), 'zef-wfgate-');
        unlink($root);
        mkdir($root . '/.github/workflows', 0o777, true);

        foreach ($workflows as $name => $contents) {
            file_put_contents($root . '/.github/workflows/' . $name, $contents);
        }

        try {
            return new WorkflowConcurrencyGate()->evaluate(WorkflowConcurrencyReport::collect($root));
        } finally {
            foreach (array_keys($workflows) as $name) {
                unlink($root . '/.github/workflows/' . $name);
            }

            rmdir($root . '/.github/workflows');
            rmdir($root . '/.github');
            rmdir($root);
        }
    }
}
