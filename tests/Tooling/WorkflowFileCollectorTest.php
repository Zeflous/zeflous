<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\WorkflowFileCollector;

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
        mkdir($this->root . '/.github/workflows/0-nested', 0o777, true);
        file_put_contents($this->root . '/.github/workflows/0-nested/c.yml', "name: C\n");

        try {
            $workflows = new WorkflowFileCollector($this->root)->collect();

            self::assertArrayNotHasKey('c.yml', $workflows);
            self::assertSame(['a.yaml', 'b.yml'], array_keys($workflows));
        } finally {
            unlink($this->root . '/.github/workflows/0-nested/c.yml');
            rmdir($this->root . '/.github/workflows/0-nested');
        }
    }

    public function testAGlobMetacharacterRootStillCollects(): void
    {
        // A checkout root like `/build/ci[2]/repo` made the glob
        // patterns match nothing and the audit report its own absence
        // as a pass; the directory iteration reads the same tree the
        // filesystem answers, metacharacters and all.
        $metacharRoot = (string) tempnam(sys_get_temp_dir(), 'zef-wf-ci[2]-');
        unlink($metacharRoot);
        mkdir($metacharRoot . '/.github/workflows', 0o777, true);
        file_put_contents($metacharRoot . '/.github/workflows/a.yml', "name: A\n");

        try {
            $workflows = new WorkflowFileCollector($metacharRoot)->collect();

            self::assertSame(['a.yml'], array_keys($workflows));
            self::assertSame("name: A\n", $workflows['a.yml']);
        } finally {
            unlink($metacharRoot . '/.github/workflows/a.yml');
            rmdir($metacharRoot . '/.github/workflows');
            rmdir($metacharRoot . '/.github');
            rmdir($metacharRoot);
        }
    }

    public function testReturnsEmptyWhenTheWorkflowDirectoryIsMissing(): void
    {
        $workflows = new WorkflowFileCollector('/nonexistent-root')->collect();

        self::assertSame([], $workflows);
    }

    public function testAStrayNonWorkflowFileNeverEndsTheWalk(): void
    {
        // The extension filter SKIPS a non-yml sibling, it never
        // terminates the iteration: the walk is sorted by name
        // (KDD-MO6), and `0-stray.txt` sorts before `a.yml` on every
        // host, so a walk that broke at it would leave the collection
        // empty and the concurrency audit blind to every workflow file
        // behind it -- the same deterministic order the subdirectory
        // skip (`0-nested`) rides in its own test.
        $root = (string) tempnam(sys_get_temp_dir(), 'zef-wf-skip-');
        unlink($root);
        mkdir($root . '/.github/workflows', 0o777, true);
        file_put_contents($root . '/.github/workflows/0-stray.txt', 'stray');
        file_put_contents($root . '/.github/workflows/a.yml', "name: A\n");

        try {
            $workflows = new WorkflowFileCollector($root)->collect();

            self::assertSame(['a.yml'], array_keys($workflows));
            self::assertSame("name: A\n", $workflows['a.yml']);
        } finally {
            unlink($root . '/.github/workflows/0-stray.txt');
            unlink($root . '/.github/workflows/a.yml');
            rmdir($root . '/.github/workflows');
            rmdir($root . '/.github');
            rmdir($root);
        }
    }
}
