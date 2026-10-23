<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\ReviewThread;
use Zef\Test\Support\Tooling\ReviewThreadReport;

/**
 * @internal
 */
#[CoversClass(ReviewThreadReport::class)]
final class ReviewThreadReportTest extends TestCase
{
    public function testSelectsOnlyFinishedConversations(): void
    {
        $reviewThreadReport = new ReviewThreadReport([
            new ReviewThread('PRRT_live', false, false, 'src/A.php'),
            new ReviewThread('PRRT_done', false, true, 'src/B.php'),
            new ReviewThread('PRRT_already', true, true, 'src/C.php'),
        ]);

        self::assertSame(3, $reviewThreadReport->count());
        self::assertSame(['PRRT_done'], $reviewThreadReport->resolvableIds());
    }

    public function testSelectsEveryFinishedConversationInPayloadOrder(): void
    {
        $reviewThreadReport = new ReviewThreadReport([
            new ReviewThread('PRRT_live', false, false, 'src/A.php'),
            new ReviewThread('PRRT_first', false, true, 'src/B.php'),
            new ReviewThread('PRRT_second', false, true, 'src/C.php'),
            new ReviewThread('PRRT_third', false, true, 'src/D.php'),
        ]);

        self::assertSame(4, $reviewThreadReport->count());
        self::assertSame(
            ['PRRT_first', 'PRRT_second', 'PRRT_third'],
            $reviewThreadReport->resolvableIds(),
        );
    }

    public function testAnEmptyReportHasNoResolvableIds(): void
    {
        $reviewThreadReport = new ReviewThreadReport([]);

        self::assertSame(0, $reviewThreadReport->count());
        self::assertSame([], $reviewThreadReport->resolvableIds());
    }

    /**
     * The unusable count's default is an honest zero: a report built
     * without an explicit count carried no node the sweep could not
     * account for, and a negative or padded default would name
     * unaccounted threads that were never there.
     */
    public function testAReportWithoutAnExplicitUnusableCountCarriesZero(): void
    {
        self::assertSame(0, new ReviewThreadReport([])->unusable);
        self::assertSame(
            0,
            new ReviewThreadReport([new ReviewThread('PRRT_live', false, false, 'src/A.php')])->unusable,
        );
    }
}
