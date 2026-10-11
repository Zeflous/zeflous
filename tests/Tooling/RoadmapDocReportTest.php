<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapDocReport;

/**
 * @internal
 */
#[CoversClass(RoadmapDocReport::class)]
final class RoadmapDocReportTest extends TestCase
{
    public function testCountsTheDocuments(): void
    {
        $roadmapDocReport = new RoadmapDocReport(['A.md' => 'a', 'B.md' => 'b']);

        self::assertSame(2, $roadmapDocReport->count());
    }
}
