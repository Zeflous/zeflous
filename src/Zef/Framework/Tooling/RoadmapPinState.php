<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The git-side inputs of the roadmap pin-freshness rule.
 *
 * Pure data: the commit `ROADMAP-STATUS.md` pins as the checked main commit,
 * whether that commit is an ancestor of the commit under validation, the
 * subjects of every commit between the pin and that commit, and the subjects
 * of the MERGE commits among them. Building it from a real checkout is the
 * job of {@see RoadmapPinCommitCollector}, so the verdict
 * ({@see RoadmapPinFreshness}) stays a pure function and runs identically in
 * a unit test.
 */
final readonly class RoadmapPinState
{
    /**
     * @param null|string  $pinnedSha     the pinned commit, or null when the status document carries no pin at all
     * @param list<string> $subjects      commit subjects between the pin and the validated commit, oldest first
     * @param list<string> $mergeSubjects subjects of the MERGE commits of that same walk; empty by default, which
     *                                    leaves the recorded-merge acceptance refusing every merge-shaped subject (fail
     *                                    closed) -- only the collector populates it from a real walk
     */
    public function __construct(
        public ?string $pinnedSha,
        public bool $pinIsAncestor,
        public array $subjects,
        public array $mergeSubjects = [],
    ) {
    }
}
