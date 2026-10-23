<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The pin-freshness verdict of the roadmap document gate.
 *
 * It closes the gap the document-internal rules cannot see: a `Last updated`
 * pin in `ROADMAP-STATUS.md` that names a commit of `main` older than the
 * changes that actually landed on it. The pointer rule
 * ({@see RoadmapPointerRules}) judges the pin against the document's own
 * newest recorded row; this rule judges it against the branch itself, through
 * {@see RoadmapPinState} gathered by {@see RoadmapPinCommitCollector}.
 *
 * Not every commit between the pin and the validated commit demands a
 * refresh. The changelog lanes regenerate their documents after every merge,
 * and a pin refresh itself lands as commits above the pin it just set, and
 * the roadmap lanes' record commits do the same (the pull request that
 * records a merge refreshes the pin, so its own commits sit above the pin by
 * construction -- a rule that refused them could never return to green), and
 * the branch-update machinery merges `main` into whatever pull-request
 * branch it refreshes -- `feat`, `fix`, `docs`, `test`, `chore` alike -- and
 * those merges ride into main's history when the pull request lands. The
 * bookkeeping patterns name exactly those: changelog regenerations,
 * roadmap-document commits (every `docs(roadmap):` subject -- pin refreshes,
 * history records, status-meter updates alike), and the update machinery's
 * `main`-into-branch merges of every branch kind. The update machinery has
 * been observed spelling that merge four ways -- `Merge branch 'main' into
 * <branch>` from its own git invocation, `Merge remote-tracking branch
 * 'origin/main' into <branch>` from the web UI's update button, the shorthand
 * `Merge origin/main into <branch>`, and the descriptive `chore: merge main
 * (<what was merged>) into <branch>` -- and a spelling the patterns refuse
 * turned the main lane red on 2026-10-22 for exactly that reason, so all
 * four observed spellings are named. An update merge is safe to accept
 * whatever the branch and whatever the spelling: it reflects main's own
 * commits back into a branch, and any real content still reaches main
 * through a pull-request merge commit of its own, which the patterns refuse.
 *
 * A merge commit that lands a pull request on `main` is accepted when -- and
 * only when -- the ledger already records that pull request: the merge
 * machinery stamps every landing commit with the pull request's title and a
 * trailing "(#<number>)", and a `ROADMAP-HISTORY.md` row carrying that number
 * in the tree under validation is the record that the landing is accounted
 * for. {@see RoadmapRecordedMerge} owns that acceptance -- the subject must
 * belong to a MERGE commit of the walk, and the number must carry a history
 * row in the validated tree. This is the acceptance that makes the rule
 * enforceable at the merge queue ({@see RoadmapPinCommitCollector} judges
 * the group head): a substantive pull request merges through the queue only
 * when its history row and its pin refresh ride in the pull request itself,
 * so the walk from the refreshed pin sees nothing but bookkeeping and the
 * recorded landing. Before this acceptance existed, the queue let an
 * unrecorded substantive merge land and the main lane went red after it --
 * the 2026-10-22 incident class, healed only reactively by a pin pull
 * request that chased the merge; with the queue judging and the recorded
 * landing accepted, the failure surfaces on the pull request's own queue
 * entry, where it can be acted on, and `main` never reddens in the first
 * place. The acceptance is fail closed on every axis: a subject not spelled
 * with the trailing number, a number with no history row, a plain
 * (non-merge) commit spelled with the suffix, or a ledger-less walk -- each
 * refuses.
 *
 * So the steady state after a refresh stays green, while any other landed
 * change (a feature, a fix, a documentation change) leaves the pin stale
 * until it is refreshed. That is the rule the document states for itself:
 * the pin names the main commit that was checked.
 *
 * The verdict is a pure function over its inputs; a lane that is not
 * validating `main` hands over a null state and is not judged here.
 */
final class RoadmapPinFreshness
{
    /**
     * Subjects that keep the record current rather than change the code: the
     * changelog lanes' regenerations (their exact, generator-controlled
     * shapes, with and without the pull-request suffix), the roadmap lanes'
     * document commits (every `docs(roadmap):` subject: pin refreshes,
     * history records, status-meter updates alike), and the branch-update
     * machinery's `main`-into-branch merges of every branch kind in all four
     * of its observed spellings (its own git invocation, the web UI's update
     * button, its shorthand, and its descriptive `chore: merge main (...)`
     * custom subject -- the parenthetical names what was merged, so a bare
     * `chore: merge main into ...` without one stays refused).
     */
    private const array BOOKKEEPING = [
        '/^chore\(changelog(?:-official)?\): regenerate (?:API|official) changelog(?: \(#\d+\))?$/',
        '/^docs\(roadmap\): /',
        "/^Merge branch 'main' into /",
        "/^Merge remote-tracking branch 'origin\\/main' into /",
        '/^Merge origin\/main into /',
        '/^chore: merge main \([^)]+\) into /',
    ];

    /**
     * The pass-message suffix that says whether the pin was judged: the
     * verdict's phrasing belongs with the verdict, so the gate stays a thin
     * composer.
     */
    public static function suffix(?RoadmapPinState $roadmapPinState): string
    {
        return $roadmapPinState instanceof RoadmapPinState
            ? ' (pin freshness verified on main).'
            : ' (pin freshness not judged: the lane is not validating main).';
    }

    /**
     * Every pin-freshness violation for one status document, in a stable
     * order.
     *
     * @param list<int> $recordedPullRequests numbers that carry a history row in the validated tree
     *
     * @return list<string>
     */
    public static function failures(string $name, ?RoadmapPinState $roadmapPinState, array $recordedPullRequests): array
    {
        if (!$roadmapPinState instanceof RoadmapPinState) {
            return [];
        }

        $pinnedSha = $roadmapPinState->pinnedSha;

        if ($pinnedSha === null || !$roadmapPinState->pinIsAncestor) {
            return [self::unusableMessage($name, $pinnedSha)];
        }

        return self::landedCommits($name, $pinnedSha, $roadmapPinState, $recordedPullRequests);
    }

    /**
     * The message for a pin the verdict cannot even trust: a status document
     * with no pin on the main lane, or a pin that does not name a commit of
     * the main lineage -- the only two ways to arrive here.
     */
    private static function unusableMessage(string $name, ?string $pinnedSha): string
    {
        if ($pinnedSha === null) {
            return \sprintf(
                '%s carries no "Last updated" pin while the main lane is validating it.',
                $name,
            );
        }

        return \sprintf(
            '%s "Last updated" pin `%s` is not an ancestor of the validated commit:'
                . ' it does not name a commit of the main lineage.',
            $name,
            $pinnedSha,
        );
    }

    /**
     * The violation of a usable pin: commits that landed on main after it and
     * are not changelog bookkeeping, roadmap bookkeeping, or the recorded
     * landing of a pull request the ledger already carries.
     *
     * @param list<int> $recordedPullRequests numbers that carry a history row in the validated tree
     *
     * @return list<string>
     */
    private static function landedCommits(
        string $name,
        string $pinnedSha,
        RoadmapPinState $roadmapPinState,
        array $recordedPullRequests,
    ): array {
        $landed = [];

        foreach ($roadmapPinState->subjects as $subject) {
            if (self::isBookkeeping($subject)) {
                continue;
            }

            $isRecordedLanding = RoadmapRecordedMerge::isAccepted(
                $subject,
                $roadmapPinState->mergeSubjects,
                $recordedPullRequests,
            );

            if ($isRecordedLanding) {
                continue;
            }

            $landed[] = $subject;
        }

        if ($landed === []) {
            return [];
        }

        return [\sprintf(
            '%s "Last updated" pin `%s` is stale: %d commit(s) landed on main after it'
                . ' that are not changelog, roadmap bookkeeping, or a recorded pull-request merge,'
                . ' starting with "%s". Refresh the pin (CONTRIBUTING.md, rule 13).',
            $name,
            $pinnedSha,
            \count($landed),
            $landed[0],
        )];
    }

    /**
     * Whether a commit subject is one of the record-keeping shapes the
     * changelog and pin lanes produce.
     */
    private static function isBookkeeping(string $subject): bool
    {
        return array_any(self::BOOKKEEPING, static fn (string $pattern): bool => preg_match($pattern, $subject) === 1);
    }
}
