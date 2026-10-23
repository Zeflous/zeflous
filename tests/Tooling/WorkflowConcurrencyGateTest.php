<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\BashArrayExpansionDetector;
use Zef\Test\Support\Tooling\GateResult;
use Zef\Test\Support\Tooling\ToolingException;
use Zef\Test\Support\Tooling\WorkflowConcurrencyGate;
use Zef\Test\Support\Tooling\WorkflowConcurrencyPolicy;
use Zef\Test\Support\Tooling\WorkflowConcurrencyReport;
use Zef\Test\Support\Tooling\WorkflowFileCollector;

/**
 * @internal
 */
#[CoversClass(WorkflowConcurrencyGate::class)]
#[CoversClass(WorkflowConcurrencyReport::class)]
#[CoversClass(WorkflowFileCollector::class)]
#[CoversClass(BashArrayExpansionDetector::class)]
#[CoversClass(WorkflowConcurrencyPolicy::class)]
final class WorkflowConcurrencyGateTest extends TestCase
{
    private const string COMMIT_GROUP = 'ci-strict-${{ github.event_name }}-'
        . '${{ github.event.pull_request.head.sha || github.sha }}';

    private const string PUSH_GROUP = 'ci-strict-${{ github.event_name }}-${{ github.sha }}';

    private const string HEAD_GROUP = 'ci-strict-${{ github.event_name }}-'
        . '${{ github.event.pull_request.head.sha }}';

    private const string BRANCH_GROUP = 'ci-strict-${{ github.event_name }}-${{ github.ref }}';

    private const string LANE_BRANCH_GROUP = 'ci-coverage-${{ github.event_name }}-${{ github.ref }}';

    private const string GITHUB_REF_EXPRESSION = '${{ github.ref }}';

    private const string LANE_COMMIT_GROUP = 'ci-coverage-${{ github.event_name }}-'
        . '${{ github.event.pull_request.head.sha || github.sha }}';

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
            'Workflow concurrency gate FAILED: ci-strict.yml declares no concurrency group at all '
            . '-- every workflow that triggers itself must key one (a reusable workflow_call file '
            . 'is exempt: its caller owns the group).',
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

    public function testIgnoresBranchKeyedGroupsOnNonCancellingWorkflows(): void
    {
        $gateResult = $this->gate([
            'ci-static.yml' => "concurrency:\n  group: " . self::BRANCH_GROUP . "\n  cancel-in-progress: false\n",
        ]);

        self::assertTrue($gateResult->passed);
    }

    /**
     * Every group of a cancelling workflow is audited on its own: two
     * branch-keyed groups on one non-aggregator file answer two
     * failures -- the walk continues past the group whose
     * aggregator-only rule does not apply, so the second group's
     * cancellation danger is never left unaudited behind the first.
     */
    public function testEveryGroupOfACancellingWorkflowIsAuditedOnItsOwn(): void
    {
        $contents = "concurrency:\n"
            . '  group: ' . self::LANE_BRANCH_GROUP . "\n"
            . "  cancel-in-progress: true\n"
            . "jobs:\n"
            . "  other:\n"
            . "    concurrency:\n"
            . '      group: refs/heads/${{ github.ref }}-nightly' . "\n"
            . "      cancel-in-progress: true\n";

        $failures = WorkflowConcurrencyPolicy::failures('ci-coverage.yml', $contents);

        self::assertCount(2, $failures);
        self::assertStringContainsString(
            'ci-coverage.yml cancels in progress but its concurrency group is not keyed on the commit sha '
            . '(found: ' . self::LANE_BRANCH_GROUP . ').',
            $failures[0],
        );
        self::assertStringContainsString(
            '(found: refs/heads/${{ github.ref }}-nightly).',
            $failures[1],
        );
    }

    public function testFailsWhenACancellingWorkflowKeysItsGroupOnTheMutatingRef(): void
    {
        $gateResult = $this->gate([
            'ci-coverage.yml' => "concurrency:\n  group: " . self::LANE_BRANCH_GROUP . "\n  cancel-in-progress: true\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Workflow concurrency gate FAILED: ci-coverage.yml cancels in progress but its concurrency group '
            . 'is not keyed on the commit sha (found: ' . self::LANE_BRANCH_GROUP . ').',
            $gateResult->message,
        );
    }

    public function testFailsWhenACancellingWorkflowHasNoGroupAtAll(): void
    {
        $gateResult = $this->gate([
            'ci-coverage.yml' => "concurrency:\n  cancel-in-progress: true\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Workflow concurrency gate FAILED: ci-coverage.yml declares no concurrency group at all '
            . '-- every workflow that triggers itself must key one (a reusable workflow_call file '
            . 'is exempt: its caller owns the group).',
            $gateResult->message,
        );
    }

    public function testAcceptsACancellingWorkflowThatKeysItsGroupOnTheCommit(): void
    {
        $gateResult = $this->gate([
            'ci-coverage.yml' => "concurrency:\n  group: " . self::LANE_COMMIT_GROUP . "\n  cancel-in-progress: true\n",
        ]);

        self::assertTrue($gateResult->passed);
    }

    public function testFailsOnACancellingWorkflowSpelledWithACapitalisedBoolean(): void
    {
        $gateResult = $this->gate([
            'ci-coverage.yml' => "concurrency:\n  group: " . self::LANE_BRANCH_GROUP . "\n  cancel-in-progress: True\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('cancels in progress', $gateResult->message);
    }

    public function testFailsOnACancellingWorkflowSpelledWithAnAllCapsBoolean(): void
    {
        $gateResult = $this->gate([
            'ci-coverage.yml' => "concurrency:\n  group: " . self::LANE_BRANCH_GROUP . "\n  cancel-in-progress: TRUE\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('cancels in progress', $gateResult->message);
    }

    public function testTreatsADynamicCancelExpressionAsNotCancelling(): void
    {
        $gateResult = $this->gate([
            'ci-coverage.yml' => "concurrency:\n  group: " . self::LANE_BRANCH_GROUP
                . "\n  cancel-in-progress: \${{ github.event_name == 'push' }}\n",
        ]);

        self::assertTrue($gateResult->passed);
    }

    public function testAJobLevelGroupIsAuditedLikeTheWorkflowLevelOne(): void
    {
        // The first-only read left every group after the first
        // invisible; a cancelling file whose second (job-level) group
        // is branch-keyed is the violation the first group's clean
        // sha key must not hide.
        $gateResult = $this->gate([
            'release.yml' => "name: Release\n"
                . "concurrency:\n  group: release-\${{ github.sha }}\n"
                . "jobs:\n  publish:\n"
                . "    concurrency:\n"
                . "      group: publish-\${{ github.ref }}\n"
                . "      cancel-in-progress: true\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString(
            'cancels in progress but its concurrency group is not keyed on the commit sha (found: publish-',
            $gateResult->message,
        );
    }

    /**
     * The exact first-only escape: a workflow whose workflow-level block
     * refuses (`cancel-in-progress: false`) while its job-level block
     * cancels (`true`) is a CANCELLING file -- the common "cancel stale
     * job runs" shape. Trusting the first spelling alone read the file as
     * non-cancelling and let the job-level branch-keyed group escape the
     * deadlock rule this gate exists to enforce.
     */
    public function testAJobLevelCancellingBlockIsJudgedDespiteARefusingWorkflowLevelOne(): void
    {
        $gateResult = $this->gate([
            'release.yml' => "name: Release\n"
                . "concurrency:\n"
                . '  group: release-${{ github.event_name }}-${{ github.sha }}' . "\n"
                . "  cancel-in-progress: false\n"
                . "jobs:\n  publish:\n"
                . "    concurrency:\n"
                . "      group: publish-\${{ github.ref }}\n"
                . "      cancel-in-progress: true\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString(
            'release.yml cancels in progress but its concurrency group is not keyed on the commit sha (found: publish-'
            . self::GITHUB_REF_EXPRESSION . ').',
            $gateResult->message,
        );
    }

    /**
     * A trailing comment is not part of the flag: `true # keep the newest`
     * spells the cancelling boolean, and the first-only literal lookup that
     * captured `true # keep` whole read the file as non-cancelling.
     */
    public function testATrailingCommentOnTheCancellingFlagIsNotPartOfTheFlag(): void
    {
        $gateResult = $this->gate([
            'ci-coverage.yml' => "concurrency:\n  group: " . self::LANE_BRANCH_GROUP
                . "\n  cancel-in-progress: true # keep the newest run\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('cancels in progress', $gateResult->message);
    }

    public function testASingleQuotedCancellingFlagReadsAsTheBoolean(): void
    {
        $gateResult = $this->gate([
            'ci-coverage.yml' => "concurrency:\n  group: " . self::LANE_BRANCH_GROUP
                . "\n  cancel-in-progress: 'true'\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('cancels in progress', $gateResult->message);
    }

    public function testADoubleQuotedCancellingFlagReadsAsTheBoolean(): void
    {
        $gateResult = $this->gate([
            'ci-coverage.yml' => "concurrency:\n  group: " . self::LANE_BRANCH_GROUP
                . "\n  cancel-in-progress: \"true\"\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('cancels in progress', $gateResult->message);
    }

    /**
     * Normalisation must not manufacture cancellations: a refusing flag
     * with a comment, and a refusing flag in quotes, stay non-cancelling.
     */
    public function testARefusingFlagWithACommentStaysNonCancelling(): void
    {
        $gateResult = $this->gate([
            'ci-static.yml' => "concurrency:\n  group: " . self::BRANCH_GROUP
                . "\n  cancel-in-progress: false # the aggregator must queue, not cancel\n",
        ]);

        self::assertTrue($gateResult->passed);
    }

    /**
     * A workflow file the audit cannot read refuses the whole collection:
     * its concurrency misconfiguration would otherwise escape the gate
     * silently. A dangling symlink is the shape that reaches the unreadable
     * arm while the directory itself still iterates; the error handler
     * swallows the engine's diagnostic, the branch under test is the
     * refusal.
     */
    public function testAnUnreadableWorkflowFileRefusesTheCollection(): void
    {
        $root = (string) tempnam(sys_get_temp_dir(), 'zef-wfcol-');
        unlink($root);
        mkdir($root . '/.github/workflows', 0o777, true);
        symlink($root . '/.github/workflows/target-that-does-not-exist.yml', $root . '/.github/workflows/dangling.yml');

        set_error_handler(static fn (): bool => true);

        try {
            new WorkflowFileCollector($root)->collect();
            self::fail('An unreadable workflow file must refuse the collection.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'Unable to read the workflow file "' . $root . '/.github/workflows/dangling.yml"'
                . ' for the concurrency audit.',
                $toolingException->getMessage(),
            );
        } finally {
            restore_error_handler();
            unlink($root . '/.github/workflows/dangling.yml');
            rmdir($root . '/.github/workflows');
            rmdir($root . '/.github');
            rmdir($root);
        }
    }

    public function testATriggeringWorkflowWithoutAnyConcurrencyGroupFails(): void
    {
        $gateResult = $this->gate([
            'nightly.yml' => "name: Nightly\non:\n  schedule:\n    - cron: '0 3 * * *'\njobs:\n  x:\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString(
            'nightly.yml declares no concurrency group at all',
            $gateResult->message,
        );
    }

    public function testAReusableWorkflowCallFileStaysExempt(): void
    {
        // A called workflow's concurrency is its caller's: the file
        // that only answers calls has no group of its own to key.
        $gateResult = $this->gate([
            'sbom-generate.yml' => "name: SBOM\non:\n  workflow_call:\njobs:\n  generate:\n",
        ]);

        self::assertTrue($gateResult->passed);
    }

    public function testReportsFailuresFromEveryWorkflow(): void
    {
        $gateResult = $this->gate([
            'a.yml' => "concurrency:\n  group: a-\${{ github.ref }}\n  cancel-in-progress: true\n",
            'b.yml' => "concurrency:\n  group: b-\${{ github.ref }}\n  cancel-in-progress: true\n",
        ]);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString(
            'a.yml cancels in progress but its concurrency group is not keyed on the commit sha (found: a-'
            . self::GITHUB_REF_EXPRESSION . ').',
            $gateResult->message,
        );
        self::assertStringContainsString(
            'b.yml cancels in progress but its concurrency group is not keyed on the commit sha (found: b-'
            . self::GITHUB_REF_EXPRESSION . ').',
            $gateResult->message,
        );
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

    public function testReportsCancellationAndAggregatorFailuresTogether(): void
    {
        $contents = "name: CI Strict\n"
            . "concurrency:\n"
            . '  group: ' . self::BRANCH_GROUP . "\n"
            . "  cancel-in-progress: true\n"
            . "jobs:\n"
            . "  ci-strict:\n"
            . "    steps:\n"
            . "      - run: |\n"
            . "          required=(\"CI Static\")\n"
            . "          for name in \"\${required[@]}\"; do :; done\n";

        $gateResult = $this->gate(['ci-strict.yml' => $contents]);

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Workflow concurrency gate FAILED: '
            . 'ci-strict.yml cancels in progress but its concurrency group is not keyed on the commit sha '
            . '(found: ' . self::BRANCH_GROUP . '). '
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
            return new WorkflowConcurrencyGate()->evaluate(new WorkflowConcurrencyReport(
                new WorkflowFileCollector($root)->collect(),
            ));
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
