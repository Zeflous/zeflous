<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapSectionHeading;

/**
 * @internal
 */
#[CoversClass(RoadmapSectionHeading::class)]
final class RoadmapSectionHeadingTest extends TestCase
{
    public function testReadsTheTitleOfANumberedSectionHeading(): void
    {
        self::assertSame('container', RoadmapSectionHeading::title('## 1. Container'));
        self::assertSame('zerotest', RoadmapSectionHeading::title('## 0. Zero-test'));
        self::assertSame('middlewarepipeline', RoadmapSectionHeading::title('## 12. Middleware Pipeline'));
    }

    public function testTheTitleIsReducedToItsComparableForm(): void
    {
        // Emphasis markers and every non-alphanumeric character are removed
        // and the label lower-cased: the comparison form, not the prose.
        self::assertSame(
            'middlewarepipeline',
            RoadmapSectionHeading::title('## 3. Middleware - **Pipeline**!'),
        );
        self::assertSame('configuration', RoadmapSectionHeading::title('## 4. Configuration '));
    }

    #[DataProvider('provideALineThatIsNotANumberedHeadingAnswersNullCases')]
    public function testALineThatIsNotANumberedHeadingAnswersNull(string $line): void
    {
        self::assertNull(RoadmapSectionHeading::title($line));
    }

    /**
     * @return Iterator<string, array{string}> of the dataset name => the line that is not a numbered heading
     */
    public static function provideALineThatIsNotANumberedHeadingAnswersNullCases(): iterable
    {
        yield 'a level-one heading' => ['# 1. Container'];

        yield 'a level-three heading' => ['### 1. Container'];

        yield 'an unnumbered heading' => ['## Container'];

        yield 'a number without the spacing' => ['## 1.Container'];

        yield 'a plain text line' => ['1. Container'];

        yield 'an empty line' => [''];

        yield 'a table row' => ['| 1 | Container |'];
    }
}
