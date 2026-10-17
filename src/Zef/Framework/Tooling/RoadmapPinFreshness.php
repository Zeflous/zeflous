<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

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
 * and a pin refresh itself lands as commits above the pin it just set. The
 * bookkeeping patterns name exactly those -- changelog regenerations, roadmap
 * pin refreshes, and the changelog lanes' update-branch merges -- so the
 * steady state after a refresh stays green, while any other landed change (a
 * feature, a fix, a documentation change) leaves the pin stale until it is
 * refreshed. That is the rule the document states for itself: the pin names
 * the main commit that was checked.
 *
 * The verdict is a pure function over its inputs; a lane that is not
 * validating `main` hands over a null state and is not judged here.
 */
final class RoadmapPinFreshness
{
    /**
     * Subjects that keep the record current rather than change the code: the
     * changelog lanes' regenerations (their exact, generator-controlled
     * shapes, with and without the pull-request suffix), the roadmap pin
     * refreshes, and the changelog lanes' `main`-into-update-branch merges.
     */
    private const array BOOKKEEPING = [
        '/^chore\(changelog(?:-official)?\): regenerate (?:API|official) changelog(?: \(#\d+\))?$/',
        '/^docs\(roadmap\): pin /',
        '/^Merge branch \'main\' into chore\//',
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
     * @return list<string>
     */
    public static function failures(string $name, ?RoadmapPinState $roadmapPinState): array
    {
        if (!$roadmapPinState instanceof RoadmapPinState) {
            return [];
        }

        if ($roadmapPinState->pinnedSha === null) {
            return [\sprintf(
                '%s carries no "Last updated" pin while the main lane is validating it.',
                $name,
            )];
        }

        if (!$roadmapPinState->pinIsAncestor) {
            return [\sprintf(
                '%s "Last updated" pin `%s` is not an ancestor of the validated commit:'
                    . ' it does not name a commit of the main lineage.',
                $name,
                $roadmapPinState->pinnedSha,
            )];
        }

        $landed = [];

        foreach ($roadmapPinState->subjects as $subject) {
            if (self::isBookkeeping($subject)) {
                continue;
            }

            $landed[] = $subject;
        }

        if ($landed === []) {
            return [];
        }

        return [\sprintf(
            '%s "Last updated" pin `%s` is stale: %d commit(s) landed on main after it'
                . ' that are not changelog or pin bookkeeping, starting with "%s".'
                . ' Refresh the pin (CONTRIBUTING.md, rule 13).',
            $name,
            $roadmapPinState->pinnedSha,
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
