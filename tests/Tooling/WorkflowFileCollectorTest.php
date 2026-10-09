<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\WorkflowFileCollector;

/**
 * @internal
 */
#[CoversClass(WorkflowFileCollector::class)]
final class WorkflowFileCollectorTest extends TestCase
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
        // A workflow-shaped file OUTSIDE .github/workflows must never be
        // collected: it pins the directory operand of the path concatenation.
        file_put_contents($this->root . '/stray.yml', "name: stray\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->root . '/.github/workflows/b.yml');
        unlink($this->root . '/.github/workflows/a.yaml');
        unlink($this->root . '/.github/workflows/notes.txt');
        unlink($this->root . '/stray.yml');
        rmdir($this->root . '/.github/workflows');
        rmdir($this->root . '/.github');
        rmdir($this->root);
    }

    public function testCollectsYmlAndYamlFilesSortedByName(): void
    {
        $workflows = new WorkflowFileCollector($this->root)->collect();

        self::assertSame(['a.yaml', 'b.yml'], array_keys($workflows));
        self::assertSame("name: A\n", $workflows['a.yaml']);
        self::assertSame("name: B\n", $workflows['b.yml']);
        self::assertArrayNotHasKey('notes.txt', $workflows);
        self::assertArrayNotHasKey('stray.yml', $workflows);
    }

    public function testIgnoresNestedWorkflowDirectoriesLikeGitHubDoes(): void
    {
        mkdir($this->root . '/.github/workflows/nested', 0o777, true);
        file_put_contents($this->root . '/.github/workflows/nested/c.yml', "name: C\n");

        try {
            $workflows = new WorkflowFileCollector($this->root)->collect();

            self::assertArrayNotHasKey('c.yml', $workflows);
            self::assertSame(['a.yaml', 'b.yml'], array_keys($workflows));
        } finally {
            unlink($this->root . '/.github/workflows/nested/c.yml');
            rmdir($this->root . '/.github/workflows/nested');
        }
    }

    public function testReturnsEmptyWhenTheWorkflowDirectoryIsMissing(): void
    {
        $workflows = new WorkflowFileCollector('/nonexistent-root')->collect();

        self::assertSame([], $workflows);
    }
}
