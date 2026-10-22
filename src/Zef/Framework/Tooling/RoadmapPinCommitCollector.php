<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use Closure;

/**
 * Reads the git-side inputs of the roadmap pin-freshness rule from a checkout.
 *
 * The freshness invariant is a property of `main`, and only a lane that is
 * actually validating `main` can judge it: a pull-request lane validates the
 * pull request's merge ref, where the pin is behind by construction, and a
 * developer's checkout usually sits on a feature branch. The collector
 * therefore decides -- from the CI environment first (a pull-request lane is
 * recognised by a non-empty `GITHUB_BASE_REF`, a main lane by `GITHUB_REF`),
 * from the checked-out branch second -- whether it is on `main`. When it is
 * not, it returns null and the gate says so in its pass message instead of
 * silently skipping; the main-validation watchdog guarantees the rule does
 * run on `main`, which is the only place its verdict means anything.
 *
 * A main lane is not only the push lane. The merge queue validates the
 * combined group head it is about to make `main`'s tip -- the branch
 * `refs/heads/gh-readonly-queue/main/pr-<N>-<sha>` -- and that head is
 * exactly the commit the freshness walk would judge one lane too late: the
 * 2026-10-22 incident class was born precisely there, a substantive pull
 * request whose record did not ride along sailing through the queue and
 * reddening `main` after the landing, forcing a healing pull request to
 * catch up. The queue lane is therefore recognised as a main lane too, so
 * the walk's verdict lands where it can still REFUSE the merge -- on the
 * group's own checks -- instead of after the damage. The queue walks with
 * the same rule the push lane walks ({@see RoadmapPinFreshness}): a
 * substantive pull request merges through the queue only when its record
 * (the `ROADMAP-HISTORY.md` row) and its pin refresh ride along, because the
 * merge commit the queue lands on `main` carries the trailing
 * "(#<number>)" that the recorded-merge acceptance checks against the
 * ledger.
 *
 * When it is on a main lane, it fails closed: a git answer it cannot
 * interpret (`merge-base --is-ancestor` exits 0 for an ancestor, 1 for a
 * plain refusal, and anything else is an error it cannot convert into a
 * verdict) throws rather than pass, because an unreadable answer about the
 * pin must never be convertible into a pass.
 *
 * CI environment note: the GitHub runner exports `GITHUB_BASE_REF` as an
 * EMPTY STRING on the push, workflow_dispatch and merge_group events (the
 * variable is present but carries no base branch), so a pull-request lane is
 * recognised only by a NON-EMPTY `GITHUB_BASE_REF` -- a mere presence check
 * mistakes every main lane for a pull-request lane and the rule never judges.
 */
final readonly class RoadmapPinCommitCollector
{
    /**
     * @param Closure(string): array{0: int, 1: string} $processRunner
     */
    public function __construct(
        private string $root,
        private Closure $processRunner,
    ) {
    }

    /**
     * The pin state for the checkout, or null when this lane is not validating
     * `main` and therefore cannot judge the pin.
     *
     * @param null|string $statusContents the `ROADMAP-STATUS.md` contents, or null when the document is absent
     */
    public function state(?string $statusContents): ?RoadmapPinState
    {
        if (!$this->onMain()) {
            return null;
        }

        $pointer = $statusContents === null
            ? null
            : RoadmapPointerRules::pointer(explode("\n", $statusContents));

        if ($pointer === null) {
            return new RoadmapPinState(null, false, []);
        }

        return $this->range($pointer[1]);
    }

    /**
     * The state for a pinned commit this lane can resolve: whether it is an
     * ancestor of the validated commit, and the subjects between it and that
     * commit. A git answer outside the documented 0/1 exits throws -- an
     * unreadable answer about the pin must never be convertible into a pass.
     */
    private function range(string $pinnedSha): RoadmapPinState
    {
        $ancestorExit = $this->git(\sprintf(
            'git -C %s merge-base --is-ancestor %s HEAD',
            escapeshellarg($this->root),
            escapeshellarg($pinnedSha),
        ))[0];

        if ($ancestorExit > 1) {
            throw new ToolingException(\sprintf(
                'Roadmap pin freshness FAILED: git could not resolve the pinned commit `%s` (merge-base exited %d).',
                $pinnedSha,
                $ancestorExit,
            ));
        }

        if ($ancestorExit === 1) {
            return new RoadmapPinState($pinnedSha, false, []);
        }

        [$logExit, $logOutput] = $this->git(\sprintf(
            'git -C %s log --format=%%s %s..HEAD',
            escapeshellarg($this->root),
            escapeshellarg($pinnedSha),
        ));

        if ($logExit !== 0) {
            throw new ToolingException(\sprintf(
                'Roadmap pin freshness FAILED: git could not list the commits after the pinned `%s` (log exited %d).',
                $pinnedSha,
                $logExit,
            ));
        }

        [$mergeExit, $mergeOutput] = $this->git(\sprintf(
            'git -C %s log --format=%%s --merges %s..HEAD',
            escapeshellarg($this->root),
            escapeshellarg($pinnedSha),
        ));

        if ($mergeExit !== 0) {
            throw new ToolingException(\sprintf(
                'Roadmap pin freshness FAILED: git could not list the merge commits after the pinned `%s` (log exited %d).',
                $pinnedSha,
                $mergeExit,
            ));
        }

        return new RoadmapPinState($pinnedSha, true, $this->subjects($logOutput), $this->subjects($mergeOutput));
    }

    /**
     * Whether this lane is validating `main`: the CI main lanes -- the push
     * lane and the merge queue's group-head lane, whose head is the commit
     * the queue is about to make `main`'s tip -- are recognised from the event
     * environment, a local checkout from its branch name.
     */
    private function onMain(): bool
    {
        $baseRef = getenv('GITHUB_BASE_REF');

        if (\is_string($baseRef) && $baseRef !== '') {
            return false;
        }

        $ref = getenv('GITHUB_REF');

        if ($ref === 'refs/heads/main') {
            return true;
        }

        if (\is_string($ref) && str_starts_with($ref, 'refs/heads/gh-readonly-queue/main/')) {
            return true;
        }

        [$exit, $output] = $this->git(\sprintf(
            'git -C %s rev-parse --abbrev-ref HEAD',
            escapeshellarg($this->root),
        ));

        return $exit === 0 && mb_trim($output) === 'main';
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function git(string $command): array
    {
        return ($this->processRunner)($command);
    }

    /**
     * The commit subjects of a `git log` payload, without its blank separator
     * lines.
     *
     * @return list<string>
     */
    private function subjects(string $logOutput): array
    {
        $subjects = [];

        foreach (explode("\n", $logOutput) as $line) {
            if (mb_trim($line) === '') {
                continue;
            }

            $subjects[] = $line;
        }

        return $subjects;
    }
}
