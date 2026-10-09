<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\WorkflowConcurrencyReport;

/**
 * @internal
 */
#[CoversClass(WorkflowConcurrencyReport::class)]
final class WorkflowConcurrencyReportTest extends TestCase
{
    public function testExposesTheCollectedWorkflowMap(): void
    {
        $workflowConcurrencyReport = new WorkflowConcurrencyReport([
            'a.yml' => "name: A\n",
            'b.yml' => "name: B\n",
        ]);

        self::assertSame(['a.yml' => "name: A\n", 'b.yml' => "name: B\n"], $workflowConcurrencyReport->workflows);
        self::assertSame(2, $workflowConcurrencyReport->count());
    }

    public function testCountsAnEmptyReportAsZero(): void
    {
        $workflowConcurrencyReport = new WorkflowConcurrencyReport([]);

        self::assertSame([], $workflowConcurrencyReport->workflows);
        self::assertSame(0, $workflowConcurrencyReport->count());
    }
}
