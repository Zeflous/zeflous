<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\WorkflowConcurrencyReport;

/**
 * @internal
 */
#[CoversClass(WorkflowConcurrencyReport::class)]
final class WorkflowConcurrencyReportTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = (string) tempnam(sys_get_temp_dir(), 'zef-wf-');
        unlink($this->root);
        mkdir($this->root . '/.github/workflows', 0o777, true);
        file_put_contents($this->root . '/.github/workflows/b.yml', "name: B\n");
        file_put_contents($this->root . '/.github/workflows/a.yaml', "name: A\n");
        file_put_contents($this->root . '/.github/workflows/notes.txt', 'ignore me');
        mkdir($this->root . '/.github/workflows/nested', 0o777, true);
        file_put_contents($this->root . '/.github/workflows/nested/c.yml', "name: C\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->root . '/.github/workflows/b.yml');
        unlink($this->root . '/.github/workflows/a.yaml');
        unlink($this->root . '/.github/workflows/notes.txt');
        unlink($this->root . '/.github/workflows/nested/c.yml');
        rmdir($this->root . '/.github/workflows/nested');
        rmdir($this->root . '/.github/workflows');
        rmdir($this->root . '/.github');
        rmdir($this->root);
    }

    public function testCollectsYmlAndYamlFilesSortedByName(): void
    {
        $workflowConcurrencyReport = WorkflowConcurrencyReport::collect($this->root);

        self::assertSame(['a.yaml', 'b.yml', 'c.yml'], array_keys($workflowConcurrencyReport->workflows));
        self::assertSame("name: A\n", $workflowConcurrencyReport->workflows['a.yaml']);
        self::assertSame("name: C\n", $workflowConcurrencyReport->workflows['c.yml']);
        self::assertSame(3, $workflowConcurrencyReport->count());
    }

    public function testReturnsEmptyWhenTheWorkflowDirectoryIsMissing(): void
    {
        $workflowConcurrencyReport = WorkflowConcurrencyReport::collect('/nonexistent-root');

        self::assertSame([], $workflowConcurrencyReport->workflows);
        self::assertSame(0, $workflowConcurrencyReport->count());
    }
}
