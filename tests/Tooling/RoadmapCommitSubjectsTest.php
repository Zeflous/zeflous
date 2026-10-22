<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapCommitSubjects;

/**
 * @internal
 */
#[CoversClass(RoadmapCommitSubjects::class)]
final class RoadmapCommitSubjectsTest extends TestCase
{
    public function testTheSubjectsKeepThePayloadOrderWithoutBlankLines(): void
    {
        // The real shape of a `git log --format=%s` payload: subjects
        // separated by blank lines, a trailing newline included.
        $payload = "docs(roadmap): pin the family\n\nchore(changelog): regenerate API changelog\n\n";

        self::assertSame(
            ['docs(roadmap): pin the family', 'chore(changelog): regenerate API changelog'],
            RoadmapCommitSubjects::of($payload),
        );
    }

    public function testAnEmptyPayloadCarriesNoSubject(): void
    {
        // A pin at the validated commit itself: git answers an empty log.
        self::assertSame([], RoadmapCommitSubjects::of(''));
    }

    public function testAWhitespaceOnlyPayloadCarriesNoSubject(): void
    {
        self::assertSame([], RoadmapCommitSubjects::of("\n \n\n\t\n"));
    }

    public function testASubjectMayItselfCarryInnerBlankLookingSpacing(): void
    {
        // Only the SEPARATOR lines are folded; a subject line keeps its own
        // characters verbatim (the walk's patterns judge the exact spelling).
        self::assertSame(
            ['Merge branch \'main\' into feat/queue lane'],
            RoadmapCommitSubjects::of("Merge branch 'main' into feat/queue lane\n"),
        );
    }
}
