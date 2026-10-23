<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

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
 *
 *  3. EVERY GROUP IN THE FILE IS AUDITED. The walk reads every `group:`
 *     spelling the file carries (`preg_match_all`), not only the first --
 *     a job-level `concurrency:` block is as much a deadlock contributor
 *     as the workflow-level one, and the first-only read left it
 *     invisible. The cancelling flag is read the same way ({@see
 *     self::cancels()}): every `cancel-in-progress:` spelling the file
 *     carries is judged, because a file whose workflow-level block
 *     refuses (`false`) while a job-level block cancels (`true`) is a
 *     cancelling file -- the common "cancel stale job runs" shape -- and
 *     trusting the first spelling alone let exactly that escape.
 *
 *  4. EVERY TRIGGERING WORKFLOW DECLARES A GROUP. A workflow that
 *     triggers itself and declares no concurrency at all used to pass
 *     silently -- a blind spot inside the gate's own declared scope.
 *     Only the reusable `workflow_call` shape is exempt: a called
 *     workflow's concurrency is its caller's, so the file that never
 *     triggers itself has no group of its own to key.
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

    private const string REUSABLE_TRIGGER_KEY = '/^[ \t]*workflow_call:/m';

    /**
     * Every rule violation for one workflow file, in a stable order.
     *
     * @return list<string>
     */
    public static function failures(string $workflow, string $contents): array
    {
        preg_match_all(self::GROUP_KEY, $contents, $groupMatches);
        $groups = $groupMatches[1];

        if ($groups === []) {
            return self::noGroupFailures($workflow, $contents);
        }

        $cancels = self::cancels($contents);

        $failures = [];

        foreach ($groups as $group) {
            if (
                str_contains($group, 'github.sha')
                || str_contains($group, 'github.event.pull_request.head.sha')
            ) {
                continue;
            }

            if ($cancels) {
                $failures[] = \sprintf(
                    '%s cancels in progress but its concurrency group is not keyed on the commit sha (found: %s).',
                    $workflow,
                    $group,
                );
            }

            if ($workflow !== self::AGGREGATOR) {
                continue;
            }

            $failures[] = \sprintf(
                '%s must key its concurrency group on the commit sha, not the branch (found: %s).',
                $workflow,
                $group,
            );
        }

        return $failures;
    }

    /**
     * Whether any concurrency block in the file cancels in progress: the
     * flag is read from EVERY `cancel-in-progress:` spelling the file
     * carries -- a file whose workflow-level block refuses while a
     * job-level block cancels is a cancelling file, so the conservative
     * read judges the file by its most demanding block rather than by
     * whichever spelling happens to sit first in the file. A dynamic
     * expression stays a refusal: only the literal boolean spellings map
     * ({@see CANCELLING}), and an unreadable shape cancels nothing.
     */
    private static function cancels(string $contents): bool
    {
        preg_match_all(self::CANCEL_KEY, $contents, $cancelMatches);

        return array_any(
            $cancelMatches[1],
            static fn (string $spelling): bool => self::CANCELLING[self::normalised($spelling)] ?? false,
        );
    }

    /**
     * The comparable spelling of a captured flag: a trailing comment is
     * not part of the flag, and neither are the quotes a literal spelling
     * may carry -- `true # keep the newest` and `'true'` must read as the
     * cancelling boolean they spell.
     */
    private static function normalised(string $spelling): string
    {
        return mb_trim(mb_trim(mb_trim(explode('#', $spelling, 2)[0]), "'"), '"');
    }

    /**
     * The no-group verdict: a workflow that triggers itself declares no
     * concurrency at all -- the blind spot the gate's own scope closes.
     * The reusable `workflow_call` shape never triggers itself; its
     * caller owns the group, so the file that only answers calls stays
     * exempt.
     *
     * @return list<string>
     */
    private static function noGroupFailures(string $workflow, string $contents): array
    {
        if (preg_match(self::REUSABLE_TRIGGER_KEY, $contents) === 1) {
            return [];
        }

        return [
            \sprintf(
                '%s declares no concurrency group at all -- every workflow that triggers itself must key one '
                . '(a reusable workflow_call file is exempt: its caller owns the group).',
                $workflow,
            ),
        ];
    }
}
