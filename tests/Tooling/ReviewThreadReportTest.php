<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\ReviewThread;
use Zef\Framework\Tooling\ReviewThreadReport;

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
}
