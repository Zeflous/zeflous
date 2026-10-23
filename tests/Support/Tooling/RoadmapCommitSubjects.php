<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The commit subjects of a `git log --format=%s` payload.
 *
 * Git separates the log's entries with blank lines; the freshness walk
 * ({@see RoadmapPinFreshness}) judges the subjects alone, so the payload is
 * folded to its subject list before it becomes {@see RoadmapPinState} data.
 * The fold lives in its own class for the same reason every other roadmap
 * parsing unit does ({@see RoadmapTableCells}, {@see RoadmapHistoryRows}):
 * the repository's architecture gate scores a class that carries several
 * operator varieties above its difficulty ceiling, so each parsing surface
 * stays focused and small.
 */
final class RoadmapCommitSubjects
{
    /**
     * The subjects of a `git log` payload, without its blank separator
     * lines, in payload order.
     *
     * @return list<string>
     */
    public static function of(string $logOutput): array
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
