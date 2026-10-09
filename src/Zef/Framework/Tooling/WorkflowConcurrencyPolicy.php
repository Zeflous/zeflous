<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The concurrency-group rules that one workflow file must satisfy.
 *
 * Two deterministic rules guard the "required check never concludes" deadlock
 * class observed on this repository:
 *
 *  1. CANCELLING WORKFLOWS MUST BE COMMIT-KEYED. A workflow that declares
 *     `cancel-in-progress: true` must key its concurrency group on the COMMIT
 *     under verification. Any other key -- the mutating merge ref
 *     `refs/pull/N/merge`, the branch name, a PR number, a constant -- lets
 *     two runs for two DIFFERENT heads share one group, and GitHub's
 *     cancellation is a best-effort, unordered operation: the NEWEST run is
 *     sometimes the victim (observed on PR #136, where the lane runs for head
 *     92f182e0 were cancelled by a run of the older head 511c72c5 that
 *     entered the group one second earlier). The newest head is then left
 *     with a cancelled lane, the `CI Strict` aggregator polls it as pending
 *     for sixty minutes, times out, and the pull request stays blocked.
 *
 *  2. THE AGGREGATOR IS ALWAYS COMMIT-KEYED. The `CI Strict` aggregator must
 *     key its group on the commit sha even though it does not cancel in
 *     progress, because its check-run creation is the merge-critical path: a
 *     per-branch group lets a new run queue behind a stale one, so the new
 *     run sits `pending` with zero jobs and its required check-run is never
 *     created (the "waiting" deadlock observed on PR #123).
 */
final readonly class WorkflowConcurrencyPolicy
{
    /**
     * The aggregator workflow that preserves the required `CI Strict` context.
     */
    private const string AGGREGATOR = 'ci-strict.yml';

    /**
     * The YAML boolean spellings that make `cancel-in-progress` cancel.
     */
    private const array CANCELLING = [
        'true' => true,
        'True' => true,
        'TRUE' => true,
    ];

    private const string GROUP_KEY = '/^[ \t]*group:[ \t]*(.+?)[ \t]*$/m';

    private const string CANCEL_KEY = '/^[ \t]*cancel-in-progress:[ \t]*(.+?)[ \t]*$/m';

    /**
     * Every rule violation for one workflow file, in a stable order.
     *
     * @return list<string>
     */
    public static function failures(string $workflow, string $contents): array
    {
        preg_match(self::GROUP_KEY, $contents, $groupMatch);
        preg_match(self::CANCEL_KEY, $contents, $cancelMatch);

        $group = $groupMatch[1] ?? 'none';

        if (
            str_contains($group, 'github.sha')
            || str_contains($group, 'github.event.pull_request.head.sha')
        ) {
            return [];
        }

        $cancels = self::CANCELLING[$cancelMatch[1] ?? ''] ?? false;

        $failures = [];

        if ($cancels) {
            $failures[] = \sprintf(
                '%s cancels in progress but its concurrency group is not keyed on the commit sha (found: %s).',
                $workflow,
                $group,
            );
        }

        if ($workflow === self::AGGREGATOR) {
            $failures[] = \sprintf(
                '%s must key its concurrency group on the commit sha, not the branch (found: %s).',
                $workflow,
                $group,
            );
        }

        return $failures;
    }
}
