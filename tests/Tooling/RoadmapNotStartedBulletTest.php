<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapNotStartedBullet;

/**
 * @internal
 */
#[CoversClass(RoadmapNotStartedBullet::class)]
final class RoadmapNotStartedBulletTest extends TestCase
{
    public function testParsesAnEmDashBullet(): void
    {
        self::assertSame(
            ['HTTP Layer', 'not started beyond the shim.'],
            RoadmapNotStartedBullet::parse('- **HTTP Layer** — not started beyond the shim.'),
        );
    }

    public function testParsesAnEnDashBullet(): void
    {
        self::assertSame(
            ['Widgets', 'shipped.'],
            RoadmapNotStartedBullet::parse('- **Widgets** – shipped.'),
        );
    }

    public function testParsesAHyphenBullet(): void
    {
        self::assertSame(
            ['Widgets', 'shipped.'],
            RoadmapNotStartedBullet::parse('- **Widgets** - shipped.'),
        );
    }

    public function testTrimsTheNonBreakingSpaceAfterTheDash(): void
    {
        self::assertSame(
            ['Widgets', 'not started.'],
            RoadmapNotStartedBullet::parse("- **Widgets** —\u{00A0}not started."),
        );
    }

    public function testRejectsALineThatIsNotABullet(): void
    {
        self::assertNull(RoadmapNotStartedBullet::parse('A prose line.'));
    }

    public function testRejectsALineThatContainsThePatternButIsNotABullet(): void
    {
        // The line carries a well-formed `**Area** — claim` body but does not
        // open with the `- **` marker, so it is not a bullet. This pins the
        // marker guard against a fall-through to the body pattern.
        self::assertNull(RoadmapNotStartedBullet::parse('prefix **Area** — claim'));
    }

    public function testRejectsABulletWithoutTheDashSeparator(): void
    {
        self::assertNull(RoadmapNotStartedBullet::parse('- **HTTP Layer** without a dash separator'));
    }

    public function testDeclaresUnstartedOnTheMarkerPrefix(): void
    {
        self::assertTrue(RoadmapNotStartedBullet::declaresUnstarted('not started beyond the shim.'));
    }

    public function testDeclaresUnstartedIsCaseInsensitive(): void
    {
        self::assertTrue(RoadmapNotStartedBullet::declaresUnstarted('Not Started.'));
    }

    public function testDoesNotDeclareUnstartedWhenTheMarkerIsNotLeading(): void
    {
        self::assertFalse(RoadmapNotStartedBullet::declaresUnstarted('handler chain not started.'));
    }

    public function testDeclaresUnstartedFoldsThroughMbstringCharacterSemantics(): void
    {
        // The long s (U+017F) lower-cases to ASCII "s" under the full Unicode
        // map but not under the byte-wise `stripos`, so a claim whose "started"
        // opens with it must still be recognised as the marker. This pins the
        // mbstring fold against a byte-wise regression.
        self::assertTrue(RoadmapNotStartedBullet::declaresUnstarted("not \u{017F}tarted."));
    }
}
