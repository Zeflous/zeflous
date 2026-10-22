<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The recorded-landing acceptance of the roadmap pin-freshness walk.
 *
 * The merge machinery lands a pull request on `main` as a merge commit whose
 * subject is the pull request's title stamped with a trailing
 * `(#<number>)` -- measured from this repository's own history (`d873d71`
 * for #532, `f40faec` for #525, `6f8fecf` for #533) and from the live merge
 * queue's group heads. The `ROADMAP-HISTORY.md` ledger carries exactly that
 * number on the row that records the landing. A walk subject is therefore
 * the accounted landing of a pull request when -- and only when -- it is the
 * subject of a MERGE commit of the walk (only the machinery stamps the
 * number; a plain commit spelled with the suffix does not qualify) and its
 * number carries a history row in the tree under validation.
 *
 * This is the acceptance that makes the pin-freshness rule enforceable at
 * the merge queue ({@see RoadmapPinCommitCollector} judges the group head):
 * a substantive pull request merges through the queue only when its history
 * row and its pin refresh ride in the pull request itself, so the walk from
 * the refreshed pin sees nothing but bookkeeping and the recorded landing.
 * Before this acceptance existed, the queue let an unrecorded substantive
 * merge land and the main lane went red after it -- the 2026-10-22 incident
 * class, healed only reactively by a pin pull request that chased the merge;
 * with the queue judging and the recorded landing accepted, the failure
 * surfaces on the pull request's own queue entry, where it can be acted on,
 * and `main` never reddens in the first place.
 *
 * The membership lookups are key lookups over flipped maps rather than
 * `in_array` flags deliberately: a boolean literal is a mutation target the
 * integer ledger cannot distinguish (an `in_array` needle over an
 * `list<int>` behaves identically under strict and loose comparison for
 * every input), while removing the flip collapses the lookup onto the
 * list's own zero-based indexes -- an observable, killable difference
 * (`(#0)` would resolve to the list's first index, not to a recorded
 * number). Fail closed on every axis: a subject not among the merge
 * commits, a subject without the trailing number, a number with no history
 * row, an empty ledger -- each refuses.
 */
final class RoadmapRecordedMerge
{
    /**
     * The trailing pull-request number the merge machinery stamps on every
     * commit it lands on `main`: `<pull request title> (#<number>)`.
     */
    private const string MERGE_PULL_REQUEST = '/\s\(#(\d+)\)$/';

    /**
     * Whether a walk subject is the recorded landing of a pull request: a
     * merge commit of the walk whose trailing "(#<number>)" carries a
     * `ROADMAP-HISTORY.md` row in the tree under validation.
     *
     * @param list<string> $mergeSubjects        subjects of the walk's MERGE commits
     * @param list<int>    $recordedPullRequests pull-request numbers that carry a history row
     */
    public static function isAccepted(string $subject, array $mergeSubjects, array $recordedPullRequests): bool
    {
        $mergeCommitSubjects = array_flip($mergeSubjects);

        if (!isset($mergeCommitSubjects[$subject])) {
            return false;
        }

        if (preg_match(self::MERGE_PULL_REQUEST, $subject, $matches) !== 1) {
            return false;
        }

        $recordedNumbers = array_flip($recordedPullRequests);

        return isset($recordedNumbers[(int) $matches[1]]);
    }
}
